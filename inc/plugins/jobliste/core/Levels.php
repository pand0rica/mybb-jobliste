<?php
/**
 * jobliste - Ebenen fuer Kategorien vom Typ "levels"
 *
 * Eine levels-Kategorie hat statt manueller Unterkategorien eine geordnete Liste von
 * Ebenen (z.B. "Land", "Sektor"). Pro Ebene kommen die Auswahlmoeglichkeiten entweder aus
 * einem Auswahl-Profilfeld (select/radio/multiselect/checkbox) oder aus einer im ACP
 * gepflegten Liste. Siehe PROJECT_CONTEXT.md "Kategorietypen".
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

class Jobliste_Levels
{
    public const SOURCES = ['profilefield', 'list'];

    /** Profilfeld-Typen, deren Optionen als Auswahl taugen. */
    public const PROFILEFIELD_TYPES = ['select', 'radio', 'multiselect', 'checkbox'];

    /** Maximale Laenge eines Ebenenwerts. */
    public const MAX_VALUE = 100;

    /** @var array<int, array[]>|null category_id => Ebenen (sortiert) */
    private static ?array $cache = null;

    /** @var array<int, array|null> fid => Profilfeld-Zeile */
    private static array $profilefield_cache = [];

    public static function clear_cache(): void
    {
        self::$cache = null;
        self::$profilefield_cache = [];
    }

    /** @return array<int, array[]> */
    private static function load_all(): array
    {
        global $db;

        if (self::$cache !== null) {
            return self::$cache;
        }
        $all = [];
        $query = $db->simple_select('jobliste_category_levels', '*', '', ['order_by' => 'sort_order, id', 'order_dir' => 'ASC']);
        while ($row = $db->fetch_array($query)) {
            $row = self::normalize($row);
            $all[$row['category_id']][] = $row;
        }
        self::$cache = $all;
        return $all;
    }

    /** @return array[] Ebenen einer Kategorie in Reihenfolge */
    public static function get_levels(int $category_id): array
    {
        $all = self::load_all();
        return $all[$category_id] ?? [];
    }

    public static function get_level(int $id): ?array
    {
        foreach (self::load_all() as $levels) {
            foreach ($levels as $level) {
                if ($level['id'] === $id) {
                    return $level;
                }
            }
        }
        return null;
    }

    // --- Schreiben --------------------------------------------------------------------

    /**
     * @param array $data label, source, profilefield_fid, options (Text, eine pro Zeile)
     * @return int neue ID, 0 bei ungueltigen Daten
     */
    public static function create(int $category_id, array $data): int
    {
        global $db;

        $row = self::clean_data($data);
        if ($row === null || Jobliste_Categories::get($category_id) === null) {
            return 0;
        }
        $max = (int)$db->fetch_field($db->simple_select('jobliste_category_levels', 'MAX(sort_order) AS m', 'category_id = ' . $category_id), 'm');
        $row['category_id'] = $category_id;
        $row['sort_order'] = $max + 10;

        $id = (int)$db->insert_query('jobliste_category_levels', self::escape_row($row));
        self::clear_cache();
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        global $db;

        $row = self::clean_data($data);
        if ($row === null || self::get_level($id) === null) {
            return false;
        }
        $db->update_query('jobliste_category_levels', self::escape_row($row), 'id = ' . $id);
        self::clear_cache();
        return true;
    }

    public static function delete(int $id): void
    {
        global $db;

        if ($id > 0) {
            $db->delete_query('jobliste_category_levels', 'id = ' . $id);
            self::clear_cache();
        }
    }

    public static function delete_by_category(int $category_id): void
    {
        global $db;

        if ($category_id > 0) {
            $db->delete_query('jobliste_category_levels', 'category_id = ' . $category_id);
            self::clear_cache();
        }
    }

    /** Ebene innerhalb ihrer Kategorie nach oben/unten schieben. */
    public static function move(int $id, string $direction): bool
    {
        global $db;

        $level = self::get_level($id);
        if ($level === null || !in_array($direction, ['up', 'down'], true)) {
            return false;
        }
        $ids = array_column(self::get_levels($level['category_id']), 'id');
        $pos = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;
        if ($pos === false || $swap < 0 || $swap >= count($ids)) {
            return false;
        }
        $ids[$pos] = $ids[$swap];
        $ids[$swap] = $id;
        foreach ($ids as $index => $level_id) {
            $db->update_query('jobliste_category_levels', ['sort_order' => ($index + 1) * 10], 'id = ' . (int)$level_id);
        }
        self::clear_cache();
        return true;
    }

    /**
     * Haengt Werte an die Liste einer list-Ebene an (fehlende, ohne Duplikate). Genutzt von
     * der Migration, um Sektoren aus den Altdaten zu uebernehmen.
     */
    public static function add_list_options(int $id, array $values): void
    {
        $level = self::get_level($id);
        if ($level === null || $level['source'] !== 'list') {
            return;
        }
        $options = self::parse_list(implode("\n", array_merge(self::parse_list($level['options']), $values)));
        self::update($id, [
            'label'            => $level['label'],
            'source'           => 'list',
            'profilefield_fid' => 0,
            'options'          => implode("\n", $options),
        ]);
    }

    // --- Optionen -----------------------------------------------------------------------

    /**
     * Auswahlmoeglichkeiten einer Ebene (Rohtext, unescaped).
     *
     * @return string[]
     */
    public static function get_options(array $level): array
    {
        if ($level['source'] === 'profilefield') {
            $field = self::get_profilefield((int)$level['profilefield_fid']);
            return $field !== null ? self::parse_profilefield_options((string)$field['type']) : [];
        }
        return self::parse_list((string)$level['options']);
    }

    /**
     * Optionen aus dem "type"-Feld eines MyBB-Profilfelds ("select\nA\nB"). Nur
     * Auswahltypen; Platzhalter aus reinen Strichen ("--") werden ausgelassen.
     *
     * @return string[]
     */
    public static function parse_profilefield_options(string $type_definition): array
    {
        $lines = explode("\n", str_replace("\r", '', $type_definition));
        $type = strtolower(trim((string)array_shift($lines)));
        if (!in_array($type, self::PROFILEFIELD_TYPES, true)) {
            return [];
        }
        return array_values(array_filter(self::parse_list(implode("\n", $lines)), static function (string $option): bool {
            return !preg_match('/^-+$/', $option);
        }));
    }

    /**
     * Zeilenliste -> getrimmte, eindeutige, nicht-leere Werte (Reihenfolge bleibt).
     *
     * @return string[]
     */
    public static function parse_list(string $text): array
    {
        $result = [];
        $seen = [];
        foreach (explode("\n", str_replace("\r", '', $text)) as $line) {
            $value = self::clean_value($line);
            $key = function_exists('my_strtolower') ? my_strtolower($value) : strtolower($value);
            if ($value === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $value;
        }
        return $result;
    }

    public static function get_profilefield(int $fid): ?array
    {
        global $db;

        if ($fid <= 0) {
            return null;
        }
        if (!array_key_exists($fid, self::$profilefield_cache)) {
            $row = $db->fetch_array($db->simple_select('profilefields', 'fid, name, type', 'fid = ' . $fid));
            self::$profilefield_cache[$fid] = $row ?: null;
        }
        return self::$profilefield_cache[$fid];
    }

    /**
     * Profilfelder, die als Ebenen-Quelle taugen.
     *
     * @return array<int, string> fid => Name
     */
    public static function get_selectable_profilefields(): array
    {
        global $db;

        $fields = [];
        $query = $db->simple_select('profilefields', 'fid, name, type', '', ['order_by' => 'disporder, fid']);
        while ($row = $db->fetch_array($query)) {
            $type = strtolower(trim((string)strtok((string)$row['type'], "\n")));
            if (in_array($type, self::PROFILEFIELD_TYPES, true)) {
                $fields[(int)$row['fid']] = (string)$row['name'];
            }
        }
        return $fields;
    }

    // --- Werte eines Berufs ---------------------------------------------------------------

    /**
     * Prueft die im Formular gewaehlten Ebenenwerte einer levels-Kategorie.
     *
     * @param mixed         $input   Rohwerte (Index = Ebenenposition)
     * @param string[]|null $current bisherige Werte (beim Bearbeiten in derselben Kategorie
     *                               bleiben sie gueltig, auch wenn es die Option nicht mehr gibt)
     * @return array [bool ok, string[] values]
     */
    public static function validate_values(int $category_id, $input, ?array $current = null): array
    {
        $levels = self::get_levels($category_id);
        if (!is_array($input)) {
            $input = [];
        }
        $values = [];
        foreach ($levels as $index => $level) {
            $value = isset($input[$index]) && is_scalar($input[$index]) ? self::clean_value((string)$input[$index]) : '';
            if ($value === '') {
                return [false, []];
            }
            $allowed = in_array($value, self::get_options($level), true)
                || ($current !== null && isset($current[$index]) && $current[$index] === $value);
            if (!$allowed) {
                return [false, []];
            }
            $values[] = $value;
        }
        return [true, $values];
    }

    public static function encode_values(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }
        $values = array_values(array_filter(array_map([self::class, 'clean_value'], $values), 'strlen'));
        return $values ? (string)json_encode($values, JSON_UNESCAPED_UNICODE) : null;
    }

    /** @return string[] */
    public static function decode_values($stored): array
    {
        if (!is_string($stored) || $stored === '') {
            return [];
        }
        $decoded = json_decode($stored, true);
        if (!is_array($decoded)) {
            return [];
        }
        $values = [];
        foreach ($decoded as $value) {
            if (is_scalar($value) && ($value = self::clean_value((string)$value)) !== '') {
                $values[] = $value;
            }
        }
        return $values;
    }

    public static function clean_value(string $value): string
    {
        $value = trim($value);
        return function_exists('my_substr') ? (string)my_substr($value, 0, self::MAX_VALUE) : substr($value, 0, self::MAX_VALUE);
    }

    // --- Helfer -------------------------------------------------------------------------

    private static function clean_data(array $data): ?array
    {
        $label = trim((string)($data['label'] ?? ''));
        $label = function_exists('my_substr') ? (string)my_substr($label, 0, 100) : substr($label, 0, 100);
        $source = (string)($data['source'] ?? 'list');
        if ($label === '' || !in_array($source, self::SOURCES, true)) {
            return null;
        }
        $fid = (int)($data['profilefield_fid'] ?? 0);
        if ($source === 'profilefield' && self::get_profilefield($fid) === null) {
            return null;
        }
        return [
            'label'            => $label,
            'source'           => $source,
            'profilefield_fid' => $source === 'profilefield' ? $fid : 0,
            'options'          => $source === 'list' ? implode("\n", self::parse_list((string)($data['options'] ?? ''))) : '',
        ];
    }

    private static function escape_row(array $row): array
    {
        global $db;

        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = $db->escape_string($value);
            }
        }
        return $row;
    }

    private static function normalize(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['category_id'] = (int)$row['category_id'];
        $row['sort_order'] = (int)$row['sort_order'];
        $row['label'] = (string)$row['label'];
        $row['source'] = (string)$row['source'];
        $row['profilefield_fid'] = (int)$row['profilefield_fid'];
        $row['options'] = (string)($row['options'] ?? '');
        return $row;
    }
}
