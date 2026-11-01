<?php
/**
 * jobliste - Kategorie-Antraege
 *
 * Nutzer:innen beantragen im UserCP neue Kategorien/Arbeitsorte (jobliste_requests), das
 * Team prueft sie im ModCP: annehmen (legt die Kategorie ueber Jobliste_Categories::create()
 * an) oder mit Begruendung ablehnen. Reine Daten-/Ablaufschicht ohne Berechtigungslogik --
 * die Aufrufer (jobliste.php, modules/modcp/requests.php) pruefen Login/Team.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

class Jobliste_Requests
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /** Missbrauchsschutz: so viele offene Antraege darf eine Person gleichzeitig haben. */
    public const MAX_PENDING_PER_USER = 5;

    /** Maximale Laenge fuer Begruendung/Antwort. */
    public const MAX_TEXT = 2000;

    /** Fehlercodes von create()/approve()/reject() -- '' = ok. */
    public const ERR_NAME        = 'name_required';
    public const ERR_PARENT      = 'parent_invalid';
    public const ERR_EXISTS      = 'category_exists';
    public const ERR_DUPLICATE   = 'request_duplicate';
    public const ERR_LIMIT       = 'limit_reached';
    public const ERR_NOT_PENDING = 'not_pending';
    public const ERR_SAVE        = 'save_failed';

    /** Ist die Antragsfunktion eingeschaltet? */
    public static function enabled(): bool
    {
        global $mybb;

        return !empty($mybb->settings['jobliste_requests_enabled']);
    }

    // --- Lesen --------------------------------------------------------------------------

    public static function get(int $id): ?array
    {
        global $db;

        if ($id <= 0) {
            return null;
        }
        $row = $db->fetch_array($db->simple_select('jobliste_requests', '*', 'id = ' . $id, ['limit' => 1]));
        return $row ? self::normalize($row) : null;
    }

    /** @return array[] offene Antraege, aelteste zuerst */
    public static function get_pending(): array
    {
        return self::fetch("status = '" . self::STATUS_PENDING . "'", 'created_at', 'ASC', 0);
    }

    /** @return array[] zuletzt bearbeitete Antraege, neueste zuerst */
    public static function get_handled(int $limit = 20): array
    {
        return self::fetch("status <> '" . self::STATUS_PENDING . "'", 'handled_at', 'DESC', $limit);
    }

    /** @return array[] alle Antraege einer Person, neueste zuerst */
    public static function get_by_user(int $uid): array
    {
        if ($uid <= 0) {
            return [];
        }
        return self::fetch('uid = ' . $uid, 'created_at', 'DESC', 0);
    }

    public static function count_pending(?int $uid = null): int
    {
        global $db;

        static $total = null;

        $where = "status = '" . self::STATUS_PENDING . "'";
        if ($uid !== null) {
            $where .= ' AND uid = ' . (int)$uid;
        } elseif ($total !== null) {
            return $total; // Startseite + ModCP-Menue fragen im selben Request mehrfach
        }
        if (!$db->table_exists('jobliste_requests')) {
            return 0; // Plugin aktiv, Tabelle aber (noch) nicht angelegt
        }
        $count = (int)$db->fetch_field($db->simple_select('jobliste_requests', 'COUNT(*) AS c', $where), 'c');
        if ($uid === null) {
            $total = $count;
        }
        return $count;
    }

    // --- Schreiben ----------------------------------------------------------------------

    /**
     * Neuer Antrag.
     *
     * @return string Fehlercode (ERR_*) oder '' bei Erfolg
     */
    public static function create(int $uid, string $name, int $parent_id, string $note): string
    {
        global $db;

        $name = (string)Jobliste_Database::clean_label($name);
        if ($uid <= 0 || $name === '') {
            return self::ERR_NAME;
        }
        if (!self::is_valid_parent($parent_id, true)) {
            return self::ERR_PARENT;
        }
        if (Jobliste_Categories::find_child_by_name($parent_id, $name) !== null) {
            return self::ERR_EXISTS;
        }
        if (self::pending_exists($parent_id, $name)) {
            return self::ERR_DUPLICATE;
        }
        if (self::count_pending($uid) >= self::MAX_PENDING_PER_USER) {
            return self::ERR_LIMIT;
        }

        $id = (int)$db->insert_query('jobliste_requests', [
            'uid'        => $uid,
            'parent_id'  => $parent_id,
            'name'       => $db->escape_string($name),
            'note'       => $db->escape_string(self::clean_text($note)),
            'status'     => self::STATUS_PENDING,
            'created_at' => TIME_NOW,
        ]);
        return $id > 0 ? '' : self::ERR_SAVE;
    }

    /**
     * Antrag annehmen: legt die Kategorie (ggf. vom Team angepasst) an und verknuepft sie.
     *
     * @param int $category_id neue Kategorie-ID (Ausgabe)
     * @return string Fehlercode (ERR_*) oder '' bei Erfolg
     */
    public static function approve(int $id, string $name, int $parent_id, bool $allow_custom_text, string $response, int $handled_by, int &$category_id = 0): string
    {
        global $db;

        $request = self::get($id);
        if ($request === null || $request['status'] !== self::STATUS_PENDING) {
            return self::ERR_NOT_PENDING;
        }
        $name = (string)Jobliste_Database::clean_label($name);
        if ($name === '') {
            return self::ERR_NAME;
        }
        // Das Team darf auch unter inaktive Kategorien einsortieren.
        if (!self::is_valid_parent($parent_id, false)) {
            return self::ERR_PARENT;
        }
        if (Jobliste_Categories::find_child_by_name($parent_id, $name) !== null) {
            return self::ERR_EXISTS;
        }

        $category_id = Jobliste_Categories::create([
            'name'              => $name,
            'parent_id'         => $parent_id,
            'allow_custom_text' => $allow_custom_text ? 1 : 0,
            'type'              => 'tree',
            'is_active'         => 1,
        ]);
        if ($category_id <= 0) {
            return self::ERR_SAVE;
        }

        $db->update_query('jobliste_requests', [
            'status'         => self::STATUS_APPROVED,
            'name'           => $db->escape_string($name),
            'parent_id'      => $parent_id,
            'category_id'    => $category_id,
            'response'       => $db->escape_string(self::clean_text($response)),
            'handled_by_uid' => $handled_by,
            'handled_at'     => TIME_NOW,
        ], 'id = ' . $id);
        return '';
    }

    /**
     * @return string Fehlercode (ERR_*) oder '' bei Erfolg
     */
    public static function reject(int $id, string $response, int $handled_by): string
    {
        global $db;

        $request = self::get($id);
        if ($request === null || $request['status'] !== self::STATUS_PENDING) {
            return self::ERR_NOT_PENDING;
        }
        $db->update_query('jobliste_requests', [
            'status'         => self::STATUS_REJECTED,
            'response'       => $db->escape_string(self::clean_text($response)),
            'handled_by_uid' => $handled_by,
            'handled_at'     => TIME_NOW,
        ], 'id = ' . $id);
        return '';
    }

    /** Eigenen, noch offenen Antrag zurueckziehen (= loeschen). */
    public static function withdraw(int $id, int $uid): bool
    {
        global $db;

        $request = self::get($id);
        if ($request === null || $request['uid'] !== $uid || $request['status'] !== self::STATUS_PENDING) {
            return false;
        }
        $db->delete_query('jobliste_requests', 'id = ' . $id);
        return true;
    }

    /**
     * Account geloescht: seine Antraege entfallen, von ihm bearbeitete bleiben (ohne
     * Bearbeiter:in) erhalten.
     */
    public static function handle_user_delete(int $uid): void
    {
        global $db;

        if ($uid <= 0 || !$db->table_exists('jobliste_requests')) {
            return;
        }
        $db->delete_query('jobliste_requests', 'uid = ' . $uid);
        $db->update_query('jobliste_requests', ['handled_by_uid' => 0], 'handled_by_uid = ' . $uid);
    }

    // --- Helfer -------------------------------------------------------------------------

    /**
     * Darf unter $parent_id eine Kategorie entstehen? 0 = neue Hauptkategorie. Kategorien
     * vom Typ "levels" haben keine manuellen Unterkategorien.
     */
    public static function is_valid_parent(int $parent_id, bool $active_only): bool
    {
        if ($parent_id === 0) {
            return true;
        }
        $parent = Jobliste_Categories::get($parent_id);
        if ($parent === null || $parent['type'] === 'levels') {
            return false;
        }
        if ($active_only) {
            foreach (Jobliste_Categories::get_path($parent_id) as $ancestor) {
                if ($ancestor['is_active'] !== 1) {
                    return false;
                }
            }
        }
        return true;
    }

    /** Gibt es schon einen offenen Antrag mit gleichem Namen an gleicher Stelle? */
    private static function pending_exists(int $parent_id, string $name): bool
    {
        global $db;

        $row = $db->fetch_array($db->simple_select(
            'jobliste_requests',
            'id',
            "status = '" . self::STATUS_PENDING . "' AND parent_id = " . $parent_id
                . " AND LOWER(name) = LOWER('" . $db->escape_string($name) . "')",
            ['limit' => 1]
        ));
        return (bool)$row;
    }

    /** @return array[] */
    private static function fetch(string $where, string $order_by, string $order_dir, int $limit): array
    {
        global $db;

        if (!$db->table_exists('jobliste_requests')) {
            return [];
        }
        $options = ['order_by' => $order_by . ', id', 'order_dir' => $order_dir];
        if ($limit > 0) {
            $options['limit'] = $limit;
        }
        $rows = [];
        $query = $db->simple_select('jobliste_requests', '*', $where, $options);
        while ($row = $db->fetch_array($query)) {
            $rows[] = self::normalize($row);
        }
        return $rows;
    }

    private static function normalize(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['uid'] = (int)$row['uid'];
        $row['parent_id'] = (int)$row['parent_id'];
        $row['name'] = (string)$row['name'];
        $row['note'] = (string)($row['note'] ?? '');
        $row['status'] = (string)$row['status'];
        $row['category_id'] = (int)($row['category_id'] ?? 0);
        $row['response'] = (string)($row['response'] ?? '');
        $row['handled_by_uid'] = (int)$row['handled_by_uid'];
        $row['handled_at'] = (int)$row['handled_at'];
        $row['created_at'] = (int)$row['created_at'];
        return $row;
    }

    private static function clean_text($text): string
    {
        $text = trim((string)$text);
        return function_exists('my_substr') ? my_substr($text, 0, self::MAX_TEXT) : substr($text, 0, self::MAX_TEXT);
    }
}
