<?php
/**
 * jobliste - Kategoriebaum
 *
 * CRUD fuer jobliste_categories (adjacency list ueber parent_id, 0 = Wurzel).
 * Zyklenschutz beim Verschieben, Sortierung per sort_order (Auf/Ab), Pfad-/Baum-
 * Aufbereitung fuer Formular-Auswahl und Gesamtuebersicht. "Hat Kinder?" wird immer
 * aus den Daten abgeleitet, nie als eigene Spalte gespeichert.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

class Jobliste_Categories
{
    /** Fehlercodes von update()/delete() -- '' = ok. */
    public const ERR_NOT_FOUND    = 'not_found';
    public const ERR_NAME         = 'name_required';
    public const ERR_PARENT       = 'parent_not_found';
    public const ERR_CYCLE        = 'cycle';
    public const ERR_HAS_CHILDREN = 'has_children';
    public const ERR_LEVELS_HAS_CHILDREN = 'levels_has_children';
    public const ERR_PARENT_LEVELS = 'parent_is_levels';

    /** Kategorietypen: Baumstruktur (manuelle Unterkategorien) oder Ebenen. */
    public const TYPES = ['tree', 'levels'];

    /** Pfad-Trenner fuer die Anzeige ("Hogwarts › Lehrer"). */
    public const PATH_SEPARATOR = ' › ';

    /** @var array<int, array>|null Request-Cache aller Kategorien (id => row) */
    private static ?array $cache = null;

    public static function clear_cache(): void
    {
        self::$cache = null;
    }

    /**
     * Alle Kategorien, sortiert nach sort_order, name.
     *
     * @return array<int, array> id => row
     */
    public static function get_all(): array
    {
        global $db;

        if (self::$cache !== null) {
            return self::$cache;
        }

        $all = [];
        $query = $db->simple_select('jobliste_categories', '*', '', ['order_by' => 'sort_order, name', 'order_dir' => 'ASC']);
        while ($row = $db->fetch_array($query)) {
            $row['id'] = (int)$row['id'];
            $row['parent_id'] = (int)$row['parent_id'];
            $row['allow_custom_text'] = (int)$row['allow_custom_text'];
            $row['type'] = in_array($row['type'] ?? 'tree', self::TYPES, true) ? (string)$row['type'] : 'tree';
            $row['sort_order'] = (int)$row['sort_order'];
            $row['is_active'] = (int)$row['is_active'];
            $all[$row['id']] = $row;
        }
        self::$cache = $all;
        return $all;
    }

    public static function get(int $id): ?array
    {
        $all = self::get_all();
        return $all[$id] ?? null;
    }

    /**
     * @return array<int, int[]> parent_id => [child ids in Sortierreihenfolge]
     */
    public static function get_children_map(): array
    {
        $map = [];
        foreach (self::get_all() as $id => $row) {
            $map[$row['parent_id']][] = $id;
        }
        return $map;
    }

    public static function has_children(int $id): bool
    {
        $map = self::get_children_map();
        return !empty($map[$id]);
    }

    /**
     * Direkte Unterkategorie von $parent_id mit diesem Namen (Gross-/Kleinschreibung egal),
     * z.B. fuer den Duplikat-Check bei Kategorie-Antraegen.
     */
    public static function find_child_by_name(int $parent_id, string $name): ?array
    {
        $needle = function_exists('my_strtolower') ? my_strtolower(trim($name)) : strtolower(trim($name));
        foreach (self::get_children_map()[$parent_id] ?? [] as $id) {
            $candidate = (string)self::get($id)['name'];
            $candidate = function_exists('my_strtolower') ? my_strtolower($candidate) : strtolower($candidate);
            if ($candidate === $needle) {
                return self::get($id);
            }
        }
        return null;
    }

    public static function is_levels(int $id): bool
    {
        $category = self::get($id);
        return $category !== null && $category['type'] === 'levels';
    }

    public static function is_leaf(int $id): bool
    {
        return self::get($id) !== null && !self::has_children($id);
    }

    /**
     * Pfad von der Wurzel bis zur Kategorie (inkl. ihr selbst). Bricht bei kaputten Daten
     * (verwaister parent_id, Zyklus) sauber ab.
     *
     * @return array[] Kategorie-Zeilen, Wurzel zuerst
     */
    public static function get_path(int $id): array
    {
        $all = self::get_all();
        $path = [];
        $seen = [];
        while ($id > 0 && isset($all[$id]) && !isset($seen[$id])) {
            $seen[$id] = true;
            array_unshift($path, $all[$id]);
            $id = $all[$id]['parent_id'];
        }
        return $path;
    }

    /** Pfad als Rohtext ("Hogwarts › Lehrer"), unescaped. */
    public static function get_path_label(int $id): string
    {
        return implode(self::PATH_SEPARATOR, array_map(static function (array $row): string {
            return (string)$row['name'];
        }, self::get_path($id)));
    }

    /**
     * Alle Nachfahren-IDs einer Kategorie (ohne sie selbst).
     *
     * @return int[]
     */
    public static function get_descendant_ids(int $id): array
    {
        $map = self::get_children_map();
        $result = [];
        $stack = $map[$id] ?? [];
        while ($stack) {
            $child = array_pop($stack);
            if (isset($result[$child])) {
                continue;
            }
            $result[$child] = $child;
            foreach ($map[$child] ?? [] as $grandchild) {
                $stack[] = $grandchild;
            }
        }
        return array_values($result);
    }

    /**
     * Wuerde parent_id = $new_parent_id fuer Kategorie $id einen Zyklus erzeugen?
     * (Kategorie unter sich selbst oder unter einen eigenen Nachfahren.)
     */
    public static function would_create_cycle(int $id, int $new_parent_id): bool
    {
        if ($new_parent_id <= 0) {
            return false;
        }
        if ($new_parent_id === $id) {
            return true;
        }
        return in_array($new_parent_id, self::get_descendant_ids($id), true);
    }

    /**
     * Flache, in Baum-Reihenfolge (Tiefensuche) sortierte Liste fuer Auswahlfelder.
     *
     * @param bool $active_only Inaktive Kategorien (und alles darunter) weglassen
     * @return array[] je ['id', 'name', 'depth', 'path', 'allow_custom_text', 'is_active', 'has_children']
     */
    public static function get_flat_tree(bool $active_only = false): array
    {
        $all = self::get_all();
        $map = self::get_children_map();
        $result = [];
        $visited = [];

        $walk = static function (int $parent_id, int $depth, array $path) use (&$walk, &$result, &$visited, $all, $map, $active_only): void {
            foreach ($map[$parent_id] ?? [] as $id) {
                if (isset($visited[$id])) {
                    continue; // kaputte Daten: Zyklus
                }
                $visited[$id] = true;
                $row = $all[$id];
                if ($active_only && $row['is_active'] !== 1) {
                    continue;
                }
                $row_path = array_merge($path, [(string)$row['name']]);
                $result[] = [
                    'id'                => $id,
                    'name'              => (string)$row['name'],
                    'depth'             => $depth,
                    'path'              => implode(self::PATH_SEPARATOR, $row_path),
                    'allow_custom_text' => $row['allow_custom_text'],
                    'type'              => $row['type'],
                    'is_active'         => $row['is_active'],
                    'sort_order'        => $row['sort_order'],
                    'parent_id'         => $row['parent_id'],
                    'has_children'      => !empty($map[$id]),
                ];
                $walk($id, $depth + 1, $row_path);
            }
        };
        $walk(0, 0, []);

        return $result;
    }

    // --- Schreiben --------------------------------------------------------------------

    /**
     * @param array $data name, parent_id, allow_custom_text, type, is_active, legacy_item_id (optional)
     * @return int neue ID, 0 bei ungueltigen Daten
     */
    public static function create(array $data): int
    {
        global $db;

        $name = self::clean_name($data['name'] ?? '');
        $parent_id = (int)($data['parent_id'] ?? 0);
        if ($name === '' || ($parent_id > 0 && self::get($parent_id) === null)) {
            return 0;
        }
        if ($parent_id > 0 && self::is_levels($parent_id)) {
            return 0; // levels-Kategorien haben keine manuellen Unterkategorien
        }
        $type = (string)($data['type'] ?? 'tree');

        $insert = [
            'parent_id'         => $parent_id,
            'name'              => $db->escape_string($name),
            'allow_custom_text' => !empty($data['allow_custom_text']) ? 1 : 0,
            'type'              => in_array($type, self::TYPES, true) ? $type : 'tree',
            'sort_order'        => isset($data['sort_order']) ? (int)$data['sort_order'] : self::next_sort_order($parent_id),
            'is_active'         => !isset($data['is_active']) || !empty($data['is_active']) ? 1 : 0,
        ];
        if (isset($data['legacy_item_id']) && $data['legacy_item_id'] !== null) {
            $insert['legacy_item_id'] = (int)$data['legacy_item_id'];
        }

        $id = (int)$db->insert_query('jobliste_categories', $insert);
        self::clear_cache();
        return $id;
    }

    /**
     * @return string Fehlercode (ERR_*) oder '' bei Erfolg
     */
    public static function update(int $id, array $data): string
    {
        global $db;

        $existing = self::get($id);
        if ($existing === null) {
            return self::ERR_NOT_FOUND;
        }

        $update = [];
        if (array_key_exists('name', $data)) {
            $name = self::clean_name($data['name']);
            if ($name === '') {
                return self::ERR_NAME;
            }
            $update['name'] = $db->escape_string($name);
        }
        if (array_key_exists('parent_id', $data)) {
            $parent_id = (int)$data['parent_id'];
            if ($parent_id > 0 && self::get($parent_id) === null) {
                return self::ERR_PARENT;
            }
            if (self::would_create_cycle($id, $parent_id)) {
                return self::ERR_CYCLE;
            }
            if ($parent_id > 0 && $parent_id !== $existing['parent_id'] && self::is_levels($parent_id)) {
                return self::ERR_PARENT_LEVELS;
            }
            if ($parent_id !== $existing['parent_id']) {
                $update['parent_id'] = $parent_id;
                $update['sort_order'] = self::next_sort_order($parent_id);
            }
        }
        if (array_key_exists('allow_custom_text', $data)) {
            $update['allow_custom_text'] = !empty($data['allow_custom_text']) ? 1 : 0;
        }
        if (array_key_exists('is_active', $data)) {
            $update['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }
        if (array_key_exists('type', $data)) {
            $type = in_array((string)$data['type'], self::TYPES, true) ? (string)$data['type'] : 'tree';
            if ($type === 'levels' && $existing['type'] !== 'levels' && self::has_children($id)) {
                return self::ERR_LEVELS_HAS_CHILDREN;
            }
            $update['type'] = $type;
        }

        if ($update) {
            $db->update_query('jobliste_categories', $update, 'id = ' . $id);
            self::clear_cache();
        }
        return '';
    }

    /**
     * Loescht eine Kategorie ohne Unterkategorien. Berufe, die an ihr haengen, wandern
     * zur Elternkategorie (Wurzel -> ohne Kategorie); ihr Freitext bleibt erhalten.
     *
     * @param int $moved_jobs Anzahl umgehaengter Berufe (Ausgabe)
     * @return string Fehlercode (ERR_*) oder '' bei Erfolg
     */
    public static function delete(int $id, int &$moved_jobs = 0): string
    {
        global $db;

        $existing = self::get($id);
        if ($existing === null) {
            return self::ERR_NOT_FOUND;
        }
        if (self::has_children($id)) {
            return self::ERR_HAS_CHILDREN;
        }

        $moved_jobs = Jobliste_Database::reassign_category($id, $existing['parent_id'] > 0 ? $existing['parent_id'] : null);
        Jobliste_Levels::delete_by_category($id);
        $db->delete_query('jobliste_categories', 'id = ' . $id);
        self::clear_cache();
        return '';
    }

    /**
     * Verschiebt eine Kategorie innerhalb ihrer Geschwister eine Position nach oben/unten.
     * Normalisiert dabei die sort_order aller Geschwister auf 10, 20, 30 ...
     *
     * @param string $direction 'up' | 'down'
     */
    public static function move(int $id, string $direction): bool
    {
        global $db;

        $existing = self::get($id);
        if ($existing === null || !in_array($direction, ['up', 'down'], true)) {
            return false;
        }
        $siblings = self::get_children_map()[$existing['parent_id']] ?? [];
        $pos = array_search($id, $siblings, true);
        if ($pos === false) {
            return false;
        }
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;
        if ($swap < 0 || $swap >= count($siblings)) {
            return false;
        }
        $siblings[$pos] = $siblings[$swap];
        $siblings[$swap] = $id;

        foreach ($siblings as $index => $sibling_id) {
            $db->update_query('jobliste_categories', ['sort_order' => ($index + 1) * 10], 'id = ' . (int)$sibling_id);
        }
        self::clear_cache();
        return true;
    }

    private static function next_sort_order(int $parent_id): int
    {
        global $db;

        $max = (int)$db->fetch_field(
            $db->simple_select('jobliste_categories', 'MAX(sort_order) AS m', 'parent_id = ' . $parent_id),
            'm'
        );
        return $max + 10;
    }

    private static function clean_name($name): string
    {
        $name = trim((string)$name);
        return function_exists('my_substr') ? my_substr($name, 0, 255) : substr($name, 0, 255);
    }
}
