<?php
/**
 * jobliste - Datenzugriff (Repository) fuer jobliste_jobs und jobliste_npcs.
 *
 * Reine DB-Schicht ohne Berechtigungslogik (die liegt in Jobliste_EntityResolver).
 * Alle Werte werden hier per (int)/escape_string() abgesichert.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

class Jobliste_Database
{
    public const ENTITY_TYPES = ['user', 'relative', 'npc'];

    /** Maximale Laenge fuer custom_label / NPC-Namen (VARCHAR(255)). */
    public const MAX_LABEL = 255;

    public static function is_valid_entity_type(string $type): bool
    {
        return in_array($type, self::ENTITY_TYPES, true);
    }

    // --- Jobs: lesen --------------------------------------------------------------------

    public static function get_job(int $id): ?array
    {
        global $db;

        if ($id <= 0) {
            return null;
        }
        $row = $db->fetch_array($db->simple_select('jobliste_jobs', '*', 'id = ' . $id, ['limit' => 1]));
        return $row ? self::normalize_job($row) : null;
    }

    /**
     * @return array[] Berufe einer Entitaet, aelteste zuerst
     */
    public static function get_jobs_by_entity(string $type, int $entity_id): array
    {
        return self::get_jobs_by_entities([['entity_type' => $type, 'entity_id' => $entity_id]]);
    }

    /**
     * Berufe mehrerer Entitaeten in einer Query.
     *
     * @param array[] $entities je ['entity_type', 'entity_id']
     * @return array[]
     */
    public static function get_jobs_by_entities(array $entities): array
    {
        global $db;

        $ids_by_type = [];
        foreach ($entities as $entity) {
            $type = (string)($entity['entity_type'] ?? '');
            $id = (int)($entity['entity_id'] ?? 0);
            if ($id > 0 && self::is_valid_entity_type($type)) {
                $ids_by_type[$type][$id] = $id;
            }
        }
        if (!$ids_by_type) {
            return [];
        }

        $clauses = [];
        foreach ($ids_by_type as $type => $ids) {
            $clauses[] = "(entity_type = '" . $db->escape_string($type) . "' AND entity_id IN (" . implode(',', array_map('intval', $ids)) . "))";
        }

        $jobs = [];
        $query = $db->simple_select('jobliste_jobs', '*', implode(' OR ', $clauses), ['order_by' => 'id', 'order_dir' => 'ASC']);
        while ($row = $db->fetch_array($query)) {
            $jobs[] = self::normalize_job($row);
        }
        return $jobs;
    }

    /** Alle Berufe eines Entitaetstyps, aelteste zuerst. */
    public static function get_jobs_by_type(string $type): array
    {
        global $db;

        if (!self::is_valid_entity_type($type)) {
            return [];
        }
        $jobs = [];
        $query = $db->simple_select('jobliste_jobs', '*', "entity_type = '" . $db->escape_string($type) . "'", ['order_by' => 'id', 'order_dir' => 'ASC']);
        while ($row = $db->fetch_array($query)) {
            $jobs[] = self::normalize_job($row);
        }
        return $jobs;
    }

    /** Alle Berufe (fuer die Gesamtuebersicht). */
    public static function get_all_jobs(): array
    {
        global $db;

        $jobs = [];
        $query = $db->simple_select('jobliste_jobs', '*', '', ['order_by' => 'id', 'order_dir' => 'ASC']);
        while ($row = $db->fetch_array($query)) {
            $jobs[] = self::normalize_job($row);
        }
        return $jobs;
    }

    public static function count_jobs_for_entity(string $type, int $entity_id): int
    {
        global $db;

        if (!self::is_valid_entity_type($type) || $entity_id <= 0) {
            return 0;
        }
        return (int)$db->fetch_field($db->simple_select(
            'jobliste_jobs',
            'COUNT(*) AS c',
            "entity_type = '" . $db->escape_string($type) . "' AND entity_id = " . $entity_id
        ), 'c');
    }

    /**
     * Gefilterte, seitenweise Liste fuer die ACP-Moderation.
     *
     * @param array $filters entity_type ('' = alle), category_id (0 = alle, -1 = ohne
     *                       Kategorie), search (Freitext im custom_label)
     * @return array ['total' => int, 'rows' => array[]]
     */
    public static function search_jobs(array $filters, int $page, int $per_page): array
    {
        global $db;

        $where = self::build_search_where($filters);
        $total = (int)$db->fetch_field($db->simple_select('jobliste_jobs', 'COUNT(*) AS c', $where), 'c');

        $page = max(1, $page);
        $per_page = max(1, $per_page);
        $rows = [];
        $query = $db->simple_select('jobliste_jobs', '*', $where, [
            'order_by'    => 'id',
            'order_dir'   => 'DESC',
            'limit_start' => ($page - 1) * $per_page,
            'limit'       => $per_page,
        ]);
        while ($row = $db->fetch_array($query)) {
            $rows[] = self::normalize_job($row);
        }

        return ['total' => $total, 'rows' => $rows];
    }

    private static function build_search_where(array $filters): string
    {
        global $db;

        $where = ['1=1'];
        $type = (string)($filters['entity_type'] ?? '');
        if ($type !== '' && self::is_valid_entity_type($type)) {
            $where[] = "entity_type = '" . $db->escape_string($type) . "'";
        }
        $category_id = (int)($filters['category_id'] ?? 0);
        if ($category_id > 0) {
            $where[] = 'category_id = ' . $category_id;
        } elseif ($category_id === -1) {
            $where[] = 'category_id IS NULL';
        }
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $like = $db->escape_string_like($search);
            $where[] = "(custom_label LIKE '%" . $like . "%' OR level_values LIKE '%" . $like . "%')";
        }
        return implode(' AND ', $where);
    }

    // --- Jobs: schreiben ----------------------------------------------------------------

    /**
     * @param array $data entity_type, entity_id, category_id (?int), custom_label (?string),
     *                    level_values (?string[]), created_by_uid, legacy_job_id (optional)
     * @return int neue ID, 0 bei ungueltigen Daten
     */
    public static function insert_job(array $data): int
    {
        global $db;

        $type = (string)($data['entity_type'] ?? '');
        $entity_id = (int)($data['entity_id'] ?? 0);
        if (!self::is_valid_entity_type($type) || $entity_id <= 0) {
            return 0;
        }
        $category_id = self::nullable_int($data['category_id'] ?? null);
        $label = self::clean_label($data['custom_label'] ?? null);
        if ($category_id === null && $label === null) {
            return 0;
        }

        $insert = [
            'entity_type'    => $db->escape_string($type),
            'entity_id'      => $entity_id,
            'created_by_uid' => (int)($data['created_by_uid'] ?? 0),
            'created_at'     => TIME_NOW,
            'updated_at'     => TIME_NOW,
        ];
        if ($category_id !== null) {
            $insert['category_id'] = $category_id;
        }
        if ($label !== null) {
            $insert['custom_label'] = $db->escape_string($label);
        }
        $levels = Jobliste_Levels::encode_values(isset($data['level_values']) && is_array($data['level_values']) ? $data['level_values'] : null);
        if ($levels !== null) {
            $insert['level_values'] = $db->escape_string($levels);
        }
        if (isset($data['legacy_job_id']) && $data['legacy_job_id'] !== null) {
            $insert['legacy_job_id'] = (int)$data['legacy_job_id'];
        }

        return (int)$db->insert_query('jobliste_jobs', $insert);
    }

    /**
     * Aendert Kategorie/Freitext/Ebenenwerte eines Berufs. Die Entitaet bleibt unveraendert.
     *
     * @param string[]|null $level_values nur bei Kategorien vom Typ "levels"
     */
    public static function update_job(int $id, ?int $category_id, ?string $custom_label, ?array $level_values = null): bool
    {
        global $db;

        $category_id = self::nullable_int($category_id);
        $label = self::clean_label($custom_label);
        if ($id <= 0 || ($category_id === null && $label === null)) {
            return false;
        }
        $levels = Jobliste_Levels::encode_values($level_values);

        // NULL-Werte per Rohquery, weil update_query() NULL nicht zuverlaessig setzt.
        $db->write_query("UPDATE `" . TABLE_PREFIX . "jobliste_jobs` SET
            category_id = " . ($category_id === null ? 'NULL' : (string)$category_id) . ",
            custom_label = " . ($label === null ? 'NULL' : "'" . $db->escape_string($label) . "'") . ",
            level_values = " . ($levels === null ? 'NULL' : "'" . $db->escape_string($levels) . "'") . ",
            updated_at = " . (int)TIME_NOW . "
            WHERE id = " . $id);
        return true;
    }

    public static function delete_job(int $id): void
    {
        global $db;

        if ($id > 0) {
            $db->delete_query('jobliste_jobs', 'id = ' . $id);
        }
    }

    public static function delete_jobs_by_entity(string $type, int $entity_id): int
    {
        global $db;

        if (!self::is_valid_entity_type($type) || $entity_id <= 0) {
            return 0;
        }
        $db->delete_query('jobliste_jobs', "entity_type = '" . $db->escape_string($type) . "' AND entity_id = " . $entity_id);
        return (int)$db->affected_rows();
    }

    /** Setzt created_by_uid = 0 fuer alle von $uid angelegten Eintraege (Jobs + NPCs). */
    public static function release_created_by(int $uid): void
    {
        global $db;

        if ($uid <= 0) {
            return;
        }
        $db->update_query('jobliste_jobs', ['created_by_uid' => 0], 'created_by_uid = ' . $uid);
        $db->update_query('jobliste_npcs', ['created_by_uid' => 0], 'created_by_uid = ' . $uid);
    }

    /**
     * Haengt alle Berufe einer Kategorie um (beim Loeschen der Kategorie).
     * Berufe ohne Freitext und ohne neue Kategorie waeren danach leer -- sie bekommen
     * den alten Kategorienamen als Freitext, damit nichts verloren geht.
     *
     * @return int Anzahl betroffener Berufe
     */
    public static function reassign_category(int $old_category_id, ?int $new_category_id): int
    {
        global $db;

        if ($old_category_id <= 0) {
            return 0;
        }
        $count = (int)$db->fetch_field($db->simple_select('jobliste_jobs', 'COUNT(*) AS c', 'category_id = ' . $old_category_id), 'c');
        if ($count === 0) {
            return 0;
        }

        if ($new_category_id === null) {
            $old = Jobliste_Categories::get($old_category_id);
            $old_name = $old !== null ? (string)$old['name'] : '';
            if ($old_name !== '') {
                $db->write_query("UPDATE `" . TABLE_PREFIX . "jobliste_jobs`
                    SET custom_label = '" . $db->escape_string($old_name) . "'
                    WHERE category_id = " . $old_category_id . " AND (custom_label IS NULL OR custom_label = '')");
            }
            $db->write_query("UPDATE `" . TABLE_PREFIX . "jobliste_jobs` SET category_id = NULL, updated_at = " . (int)TIME_NOW . " WHERE category_id = " . $old_category_id);
        } else {
            $db->update_query('jobliste_jobs', ['category_id' => (int)$new_category_id, 'updated_at' => TIME_NOW], 'category_id = ' . $old_category_id);
        }
        return $count;
    }

    /** Anzahl Berufe je Kategorie (fuer ACP-Anzeige). @return array<int,int> */
    public static function count_jobs_per_category(): array
    {
        global $db;

        $counts = [];
        $query = $db->query("SELECT category_id, COUNT(*) AS c FROM `" . TABLE_PREFIX . "jobliste_jobs` WHERE category_id IS NOT NULL GROUP BY category_id");
        while ($row = $db->fetch_array($query)) {
            $counts[(int)$row['category_id']] = (int)$row['c'];
        }
        return $counts;
    }

    // --- NPCs ---------------------------------------------------------------------------

    public static function get_npc(int $id): ?array
    {
        global $db;

        if ($id <= 0) {
            return null;
        }
        $row = $db->fetch_array($db->simple_select('jobliste_npcs', '*', 'id = ' . $id, ['limit' => 1]));
        return $row ? self::normalize_npc($row) : null;
    }

    /** @return array<int, array> id => row, alphabetisch */
    public static function get_npcs(): array
    {
        global $db;

        $npcs = [];
        $query = $db->simple_select('jobliste_npcs', '*', '', ['order_by' => 'name', 'order_dir' => 'ASC']);
        while ($row = $db->fetch_array($query)) {
            $row = self::normalize_npc($row);
            $npcs[$row['id']] = $row;
        }
        return $npcs;
    }

    /** @return array<int, string> id => name fuer die uebergebenen IDs */
    public static function get_npc_names(array $ids): array
    {
        global $db;

        $ids = array_values(array_filter(array_map('intval', $ids), static function (int $id): bool {
            return $id > 0;
        }));
        if (!$ids) {
            return [];
        }
        $names = [];
        $query = $db->simple_select('jobliste_npcs', 'id, name', 'id IN (' . implode(',', $ids) . ')');
        while ($row = $db->fetch_array($query)) {
            $names[(int)$row['id']] = (string)$row['name'];
        }
        return $names;
    }

    public static function insert_npc(string $name, string $notes, int $created_by_uid): int
    {
        global $db;

        $name = self::clean_label($name);
        if ($name === null) {
            return 0;
        }
        return (int)$db->insert_query('jobliste_npcs', [
            'name'           => $db->escape_string($name),
            'notes'          => $db->escape_string(trim($notes)),
            'created_by_uid' => $created_by_uid,
            'created_at'     => TIME_NOW,
        ]);
    }

    public static function update_npc(int $id, string $name, string $notes): bool
    {
        global $db;

        $name = self::clean_label($name);
        if ($id <= 0 || $name === null) {
            return false;
        }
        $db->update_query('jobliste_npcs', [
            'name'  => $db->escape_string($name),
            'notes' => $db->escape_string(trim($notes)),
        ], 'id = ' . $id);
        return true;
    }

    /** Loescht den NPC samt seiner Berufe. */
    public static function delete_npc(int $id): void
    {
        global $db;

        if ($id <= 0) {
            return;
        }
        self::delete_jobs_by_entity('npc', $id);
        $db->delete_query('jobliste_npcs', 'id = ' . $id);
    }

    // --- Helfer -------------------------------------------------------------------------

    private static function normalize_job(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['entity_type'] = (string)$row['entity_type'];
        $row['entity_id'] = (int)$row['entity_id'];
        $row['category_id'] = $row['category_id'] === null ? null : (int)$row['category_id'];
        $row['custom_label'] = $row['custom_label'] === null ? null : (string)$row['custom_label'];
        $row['level_values'] = Jobliste_Levels::decode_values($row['level_values'] ?? null);
        $row['created_by_uid'] = (int)$row['created_by_uid'];
        $row['legacy_job_id'] = $row['legacy_job_id'] === null ? null : (int)$row['legacy_job_id'];
        $row['created_at'] = (int)$row['created_at'];
        $row['updated_at'] = (int)$row['updated_at'];
        return $row;
    }

    private static function normalize_npc(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['name'] = (string)$row['name'];
        $row['notes'] = (string)($row['notes'] ?? '');
        $row['created_by_uid'] = (int)$row['created_by_uid'];
        $row['created_at'] = (int)$row['created_at'];
        return $row;
    }

    private static function nullable_int($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (int)$value;
        return $value > 0 ? $value : null;
    }

    /** Trimmt und kuerzt auf 255 Zeichen; leer -> null. */
    public static function clean_label($label): ?string
    {
        if ($label === null) {
            return null;
        }
        $label = trim((string)$label);
        if ($label === '') {
            return null;
        }
        return function_exists('my_substr') ? my_substr($label, 0, self::MAX_LABEL) : substr($label, 0, self::MAX_LABEL);
    }
}
