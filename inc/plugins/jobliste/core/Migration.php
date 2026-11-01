<?php
/**
 * jobliste - Legacy-Migration (relatives_items, jobs, relatives.beruf, wanted.beruf)
 *
 * Siehe PROJECT_CONTEXT.md "Migration (Legacy -> Neu)". Liest die Alt-Tabellen nur
 * (table_exists()-Guards), schreibt ausschliesslich in jobliste_*-Tabellen. Die
 * Verwandten-Zuordnung laeuft ueber relatives_persons.legacy_id/legacy_source (das
 * Relatives-Plugin muss vorher installiert UND migriert sein).
 *
 * Idempotent: Kategorien werden ueber legacy_item_id, User-Berufe ueber legacy_job_id
 * und Verwandten-Berufe ueber identische Eintraege wiedererkannt -- ein erneuter Lauf
 * (force) ergaenzt nur Fehlendes.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

class Jobliste_Migration
{
    /** Trenner, mit dem mehrere Freitext-Segmente zu einem custom_label verbunden werden. */
    public const TEXT_JOIN = ' – ';

    /** Ebenen, die fuer erkannte "Ebenen"-Roots (nicht GB / Muggel) angelegt werden. */
    public const LEVEL_COUNT = 2;

    /** @return array<string, bool> */
    public static function legacy_tables_available(): array
    {
        global $db;

        $result = [];
        foreach (['relatives_items', 'jobs', 'relatives', 'wanted', 'relatives_persons'] as $table) {
            $result[$table] = $db->table_exists($table);
        }
        return $result;
    }

    public static function last_run(): int
    {
        global $db;

        return (int)$db->fetch_field($db->simple_select('settings', 'value', "name = 'jobliste_legacy_migrated'"), 'value');
    }

    /**
     * Profilfeld, das als Quelle fuer die Ebene "Land" vorgeschlagen wird: das erste
     * Auswahl-Profilfeld mit "Herkunft" im Namen, sonst 0 (= ACP-Liste aus den Altdaten).
     */
    public static function guess_origin_profilefield(): int
    {
        foreach (Jobliste_Levels::get_selectable_profilefields() as $fid => $name) {
            if (stripos($name, 'herkunft') !== false || stripos($name, 'origin') !== false) {
                return (int)$fid;
            }
        }
        return 0;
    }

    // --- Reine Parsing-Logik (ohne DB, im CLI-Selbsttest geprueft) -----------------------

    /**
     * Zerlegt einen Alt-Pfad ("1\n43\n46\n47\nSekretaerin\n") wie der Altcode: fuehrende
     * numerische Segmente sind Kategorie-IDs (relatives_items.id), ab dem ersten
     * Nicht-Zahl-Segment ist alles Freitext. Leere Segmente werden ignoriert.
     *
     * @return array ['ids' => int[], 'texts' => string[]]
     */
    public static function parse_legacy_path(string $value): array
    {
        $ids = [];
        $texts = [];
        foreach (explode("\n", str_replace("\r", '', $value)) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            if (!$texts && ctype_digit($segment)) {
                $ids[] = (int)$segment;
            } else {
                $texts[] = $segment;
            }
        }
        return ['ids' => $ids, 'texts' => $texts];
    }

    /**
     * Legacy-IDs von Roots im Sonderformat "nicht GB"/"Muggel": ein einzelnes numerisches
     * Segment direkt gefolgt von >= 3 Freitext-Segmenten (Land, Sektor, Taetigkeit).
     *
     * @param string[] $values Alt-Pfade
     * @return int[]
     */
    public static function detect_level_roots(array $values): array
    {
        $roots = [];
        foreach ($values as $value) {
            $parsed = self::parse_legacy_path((string)$value);
            if (count($parsed['ids']) === 1 && $parsed['ids'][0] > 0 && count($parsed['texts']) >= 3) {
                $roots[$parsed['ids'][0]] = $parsed['ids'][0];
            }
        }
        sort($roots);
        return array_values($roots);
    }

    /**
     * Uebersetzt einen geparsten Alt-Pfad in die neuen Felder.
     *
     * @param array<int, int> $item_map    legacy relatives_items.id => jobliste_categories.id
     * @param int[]           $level_roots Legacy-IDs, deren Kategorie Typ "levels" hat
     * @return array ['skip' => bool, 'category_id' => ?int, 'level_values' => string[],
     *                'custom_label' => ?string, 'unresolved_ids' => int[]]
     */
    public static function resolve_path(array $parsed, array $item_map, array $level_roots): array
    {
        $result = ['skip' => false, 'category_id' => null, 'level_values' => [], 'custom_label' => null, 'unresolved_ids' => []];

        $deepest_legacy = null;
        foreach ($parsed['ids'] as $legacy_id) {
            if ($legacy_id <= 0) {
                continue; // 0 = "Keine Angabe"
            }
            if (isset($item_map[$legacy_id])) {
                $result['category_id'] = (int)$item_map[$legacy_id];
                $deepest_legacy = $legacy_id;
            } else {
                $result['unresolved_ids'][] = $legacy_id;
            }
        }

        $texts = $parsed['texts'];
        if ($deepest_legacy !== null && in_array($deepest_legacy, $level_roots, true)) {
            $result['level_values'] = array_slice($texts, 0, self::LEVEL_COUNT);
            $texts = array_slice($texts, self::LEVEL_COUNT);
        }
        if ($texts) {
            $result['custom_label'] = implode(self::TEXT_JOIN, $texts);
        }

        $result['skip'] = $result['category_id'] === null && $result['custom_label'] === null && !$result['level_values'];
        return $result;
    }

    // --- Lauf -----------------------------------------------------------------------------

    /**
     * @param array $options force (bool), profilefield_fid (int, Quelle der Ebene "Land")
     * @return array Bericht; bei Abbruch ['error' => Sprach-Key]
     */
    public static function run(array $options): array
    {
        global $db;

        $force = !empty($options['force']);
        $tables = self::legacy_tables_available();

        if (self::last_run() > 0 && !$force) {
            return ['error' => 'jobliste_acp_migration_already_done'];
        }
        if (!$tables['relatives_items']) {
            return ['error' => 'jobliste_acp_migration_no_items'];
        }
        // Verwandten-Berufe brauchen das migrierte Relatives-Plugin -- lieber gar nicht
        // erst anfangen, als einen halben Datenbestand zu erzeugen.
        if (($tables['relatives'] || $tables['wanted']) && !$tables['relatives_persons']) {
            return ['error' => 'jobliste_acp_migration_no_relatives'];
        }

        $report = [
            'categories_created'   => 0,
            'categories_existing'  => 0,
            'level_categories'     => [],
            'jobs_user'            => 0,
            'jobs_relative'        => 0,
            'jobs_existing'        => 0,
            'skipped_empty'        => 0,
            'skipped_orphan_users' => 0,
            'unresolved_relatives' => [],
            'unresolved_items'     => [],
        ];

        // 1. Kategorien
        $item_map = self::migrate_categories($report);

        // Alle Alt-Pfade einsammeln (fuer die Erkennung der Ebenen-Roots)
        $user_rows = $tables['jobs'] ? self::fetch_rows('jobs', 'id, uid, value') : [];
        $relative_rows = [];
        foreach (['relatives', 'wanted'] as $source) {
            if ($tables[$source]) {
                foreach (self::fetch_rows($source, 'id, beruf', "beruf IS NOT NULL AND beruf <> ''") as $row) {
                    $row['source'] = $source;
                    $relative_rows[] = $row;
                }
            }
        }
        $all_values = array_merge(array_column($user_rows, 'value'), array_column($relative_rows, 'beruf'));

        // 2. "nicht GB"/"Muggel" -> Kategorietyp "Ebenen"
        $level_roots = self::setup_level_roots($all_values, $item_map, (int)($options['profilefield_fid'] ?? 0), $report);

        // 3. User-Berufe (jobs)
        $existing_users = [];
        if ($user_rows) {
            $uids = array_unique(array_map('intval', array_column($user_rows, 'uid')));
            $query = $db->simple_select('users', 'uid', 'uid IN (' . implode(',', $uids ?: [0]) . ')');
            while ($row = $db->fetch_array($query)) {
                $existing_users[(int)$row['uid']] = true;
            }
        }
        $imported_legacy_jobs = [];
        $query = $db->simple_select('jobliste_jobs', 'legacy_job_id', 'legacy_job_id IS NOT NULL');
        while ($row = $db->fetch_array($query)) {
            $imported_legacy_jobs[(int)$row['legacy_job_id']] = true;
        }

        foreach ($user_rows as $row) {
            $legacy_id = (int)$row['id'];
            $uid = (int)$row['uid'];
            if (isset($imported_legacy_jobs[$legacy_id])) {
                $report['jobs_existing']++;
                continue;
            }
            if (!isset($existing_users[$uid])) {
                $report['skipped_orphan_users']++;
                continue;
            }
            $resolved = self::resolve_path(self::parse_legacy_path((string)$row['value']), $item_map, $level_roots);
            self::note_unresolved($resolved, $report);
            if ($resolved['skip']) {
                $report['skipped_empty']++;
                continue;
            }
            $id = Jobliste_Database::insert_job([
                'entity_type'    => 'user',
                'entity_id'      => $uid,
                'category_id'    => $resolved['category_id'],
                'custom_label'   => $resolved['custom_label'],
                'level_values'   => $resolved['level_values'],
                'created_by_uid' => $uid,
                'legacy_job_id'  => $legacy_id,
            ]);
            if ($id > 0) {
                $report['jobs_user']++;
            }
        }

        // 4. Verwandten-Berufe (relatives.beruf / wanted.beruf)
        $person_map = $relative_rows ? self::load_person_map() : [];
        foreach ($relative_rows as $row) {
            $key = $row['source'] . ':' . (int)$row['id'];
            if (!isset($person_map[$key])) {
                $report['unresolved_relatives'][] = $key;
                continue;
            }
            $resolved = self::resolve_path(self::parse_legacy_path((string)$row['beruf']), $item_map, $level_roots);
            self::note_unresolved($resolved, $report);
            if ($resolved['skip']) {
                $report['skipped_empty']++;
                continue;
            }
            $person_id = $person_map[$key];
            if (self::relative_job_exists($person_id, $resolved)) {
                $report['jobs_existing']++;
                continue;
            }
            $id = Jobliste_Database::insert_job([
                'entity_type'    => 'relative',
                'entity_id'      => $person_id,
                'category_id'    => $resolved['category_id'],
                'custom_label'   => $resolved['custom_label'],
                'level_values'   => $resolved['level_values'],
                'created_by_uid' => 0,
            ]);
            if ($id > 0) {
                $report['jobs_relative']++;
            }
        }

        $db->update_query('settings', ['value' => (string)TIME_NOW], "name = 'jobliste_legacy_migrated'");
        rebuild_settings();

        $report['unresolved_items'] = array_values(array_unique($report['unresolved_items']));
        return $report;
    }

    /**
     * relatives_items -> jobliste_categories (Eltern zuerst). Bereits importierte Items
     * (legacy_item_id) werden wiederverwendet.
     *
     * @return array<int, int> legacy id => neue Kategorie-ID
     */
    private static function migrate_categories(array &$report): array
    {
        global $db;

        $map = [];
        $query = $db->simple_select('jobliste_categories', 'id, legacy_item_id', 'legacy_item_id IS NOT NULL');
        while ($row = $db->fetch_array($query)) {
            $map[(int)$row['legacy_item_id']] = (int)$row['id'];
        }

        $items = [];
        $children = [];
        foreach (self::fetch_rows('relatives_items', 'id, name, parent, next', '', 'id') as $row) {
            $id = (int)$row['id'];
            $items[$id] = $row;
        }
        foreach ($items as $id => $row) {
            $parent = (int)$row['parent'];
            // Verwaiste Eltern-Verweise haengen an die Wurzel.
            $children[isset($items[$parent]) ? $parent : 0][] = $id;
        }

        $queue = [0];
        $visited = [];
        while ($queue) {
            $parent = array_shift($queue);
            foreach ($children[$parent] ?? [] as $position => $legacy_id) {
                if (isset($visited[$legacy_id])) {
                    continue;
                }
                $visited[$legacy_id] = true;
                $queue[] = $legacy_id;
                if (isset($map[$legacy_id])) {
                    $report['categories_existing']++;
                    continue;
                }
                $row = $items[$legacy_id];
                $new_id = Jobliste_Categories::create([
                    'name'              => (string)$row['name'],
                    'parent_id'         => $parent > 0 ? ($map[$parent] ?? 0) : 0,
                    'allow_custom_text' => (int)$row['next'] === 2 ? 1 : 0,
                    'sort_order'        => ($position + 1) * 10,
                    'legacy_item_id'    => $legacy_id,
                ]);
                if ($new_id > 0) {
                    $map[$legacy_id] = $new_id;
                    $report['categories_created']++;
                }
            }
        }
        return $map;
    }

    /**
     * Stellt die erkannten Roots (nicht GB / Muggel) auf Typ "levels" um und legt die
     * Ebenen "Land" (Profilfeld oder Liste) und "Sektor" (Liste) an bzw. ergaenzt deren
     * Listen um die Werte aus den Altdaten.
     *
     * @return int[] Legacy-IDs, deren Kategorie jetzt Typ "levels" hat
     */
    private static function setup_level_roots(array $all_values, array $item_map, int $profilefield_fid, array &$report): array
    {
        global $lang;

        $level_roots = [];
        foreach (self::detect_level_roots($all_values) as $legacy_id) {
            if (!isset($item_map[$legacy_id])) {
                continue;
            }
            $category_id = $item_map[$legacy_id];
            $category = Jobliste_Categories::get($category_id);
            if ($category === null) {
                continue;
            }
            if ($category['type'] !== 'levels') {
                if (Jobliste_Categories::update($category_id, ['type' => 'levels', 'allow_custom_text' => 1]) !== '') {
                    continue; // hat Unterkategorien -> bleibt Baum, Texte landen im Freitext
                }
            }
            $level_roots[] = $legacy_id;
            $report['level_categories'][] = (string)$category['name'];

            // Werte je Ebene aus den Altdaten sammeln
            $collected = array_fill(0, self::LEVEL_COUNT, []);
            foreach ($all_values as $value) {
                $parsed = self::parse_legacy_path((string)$value);
                if (($parsed['ids'][count($parsed['ids']) - 1] ?? null) !== $legacy_id) {
                    continue;
                }
                foreach (array_slice($parsed['texts'], 0, self::LEVEL_COUNT) as $index => $text) {
                    $collected[$index][] = $text;
                }
            }

            $levels = Jobliste_Levels::get_levels($category_id);
            if (!$levels) {
                $use_profilefield = $profilefield_fid > 0 && Jobliste_Levels::get_profilefield($profilefield_fid) !== null;
                Jobliste_Levels::create($category_id, [
                    'label'            => $lang->jobliste_acp_migration_level_country,
                    'source'           => $use_profilefield ? 'profilefield' : 'list',
                    'profilefield_fid' => $use_profilefield ? $profilefield_fid : 0,
                    'options'          => implode("\n", $collected[0]),
                ]);
                Jobliste_Levels::create($category_id, [
                    'label'   => $lang->jobliste_acp_migration_level_sector,
                    'source'  => 'list',
                    'options' => implode("\n", $collected[1]),
                ]);
            } else {
                foreach ($levels as $index => $level) {
                    if ($level['source'] === 'list' && isset($collected[$index])) {
                        Jobliste_Levels::add_list_options($level['id'], $collected[$index]);
                    }
                }
            }
        }
        return $level_roots;
    }

    /**
     * "relatives:<alte id>" / "wanted:<alte id>" => relatives_persons.id
     *
     * @return array<string, int>
     */
    private static function load_person_map(): array
    {
        global $db;

        $map = [];
        $query = $db->simple_select('relatives_persons', 'id, legacy_id, legacy_source', "legacy_id IS NOT NULL AND legacy_source IN ('relatives', 'wanted')", ['order_by' => 'id', 'order_dir' => 'ASC']);
        while ($row = $db->fetch_array($query)) {
            $key = $row['legacy_source'] . ':' . (int)$row['legacy_id'];
            if (!isset($map[$key])) {
                $map[$key] = (int)$row['id'];
            }
        }
        return $map;
    }

    private static function relative_job_exists(int $person_id, array $resolved): bool
    {
        foreach (Jobliste_Database::get_jobs_by_entity('relative', $person_id) as $job) {
            if ($job['category_id'] === $resolved['category_id']
                && $job['custom_label'] === Jobliste_Database::clean_label($resolved['custom_label'])
                && $job['level_values'] === Jobliste_Levels::decode_values(Jobliste_Levels::encode_values($resolved['level_values']))) {
                return true;
            }
        }
        return false;
    }

    private static function note_unresolved(array $resolved, array &$report): void
    {
        foreach ($resolved['unresolved_ids'] as $legacy_id) {
            $report['unresolved_items'][] = (int)$legacy_id;
        }
    }

    /** @return array[] alle Zeilen einer (Legacy-)Tabelle */
    private static function fetch_rows(string $table, string $fields, string $where = '', string $order_by = 'id'): array
    {
        global $db;

        $rows = [];
        $query = $db->simple_select($table, $fields, $where, ['order_by' => $order_by, 'order_dir' => 'ASC']);
        while ($row = $db->fetch_array($query)) {
            $rows[] = $row;
        }
        return $rows;
    }
}
