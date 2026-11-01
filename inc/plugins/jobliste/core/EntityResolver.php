<?php
/**
 * jobliste - Entity-Resolver (Konsumentenseite der Provider-Hooks)
 *
 * Sammelt die Entitaeten, fuer die ein User Berufe eintragen darf
 * (jobliste_collect_entities), loest Anzeigenamen auf (jobliste_resolve_entity_label)
 * und prueft Berechtigungen. Vertragsregeln (PROJECT_CONTEXT.md, conventions.md §7):
 * jede Provider-Zeile laeuft durch jobliste_sanitize_entity(), Malformed wird still
 * verworfen, Escaping passiert ausschliesslich beim Rendern in Jobliste.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

/**
 * Validiert/normalisiert eine von einem Provider gelieferte Entitaets-Zeile.
 *
 * @param mixed    $row           Rohzeile aus $hook_args['entities']
 * @param string[] $allowed_types Welche entity_types ein Provider liefern darf
 * @return array|null ['entity_type', 'entity_id', 'label', 'relation_label'] oder null
 */
function jobliste_sanitize_entity($row, array $allowed_types = ['relative']): ?array
{
    if (!is_array($row)) {
        return null;
    }
    if (!isset($row['entity_type']) || !is_string($row['entity_type']) || !in_array($row['entity_type'], $allowed_types, true)) {
        return null;
    }
    if (!isset($row['entity_id']) || !is_scalar($row['entity_id']) || !preg_match('/^\d+$/', (string)$row['entity_id'])) {
        return null;
    }
    $entity_id = (int)$row['entity_id'];
    if ($entity_id <= 0) {
        return null;
    }
    if (!isset($row['label']) || !is_scalar($row['label'])) {
        return null;
    }
    $label = Jobliste_Database::clean_label((string)$row['label']);
    if ($label === null) {
        return null;
    }

    $relation_label = '';
    if (isset($row['relation_label']) && is_scalar($row['relation_label'])) {
        $relation_label = (string)Jobliste_Database::clean_label((string)$row['relation_label']);
    }

    return [
        'entity_type'    => $row['entity_type'],
        'entity_id'      => $entity_id,
        'label'          => $label,
        'relation_label' => $relation_label,
    ];
}

/**
 * Validiert die optionalen 'relations'-Zeilen aus jobliste_resolve_entity_label:
 * je ['uid' => int > 0, 'relation_label' => string (optional)]. Malformed wird still
 * verworfen, doppelte uids zusammengefasst.
 *
 * @return array[] je ['uid' => int, 'relation_label' => string]
 */
function jobliste_sanitize_relations(array $rows): array
{
    $clean = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !isset($row['uid']) || !is_scalar($row['uid']) || !preg_match('/^\d+$/', (string)$row['uid'])) {
            continue;
        }
        $uid = (int)$row['uid'];
        if ($uid <= 0 || isset($clean[$uid])) {
            continue;
        }
        $relation_label = '';
        if (isset($row['relation_label']) && is_scalar($row['relation_label'])) {
            $relation_label = (string)Jobliste_Database::clean_label((string)$row['relation_label']);
        }
        $clean[$uid] = ['uid' => $uid, 'relation_label' => $relation_label];
    }
    return array_values($clean);
}

class Jobliste_EntityResolver
{
    /** @var array<string, string|null> Request-Cache "type:id" => Label (null = unaufloesbar) */
    private static array $label_cache = [];

    /** @var array<int, array[]> Request-Cache uid => gesammelte Entitaeten */
    private static array $entity_cache = [];

    /** @var array<int, array|null> Request-Cache uid => ['username', 'usergroup', 'displaygroup'] */
    private static array $user_cache = [];

    /** @var array<int, array[]> Request-Cache relative-id => [['uid', 'relation_label'], ...] */
    private static array $relations_cache = [];

    /** @var array<int, int> Request-Cache relative-id => verknuepfte Account-uid (0 = keine) */
    private static array $linked_cache = [];

    public static function clear_cache(): void
    {
        self::$label_cache = [];
        self::$entity_cache = [];
        self::$user_cache = [];
        self::$relations_cache = [];
        self::$linked_cache = [];
    }

    /**
     * Laedt Name + Gruppen mehrerer Accounts gebuendelt in den Request-Cache
     * (fuer die Gruppenfarbe per format_name()).
     *
     * @param int[] $uids
     */
    public static function load_users(array $uids): void
    {
        global $db;

        $need = [];
        foreach ($uids as $uid) {
            $uid = (int)$uid;
            if ($uid > 0 && !array_key_exists($uid, self::$user_cache)) {
                $need[$uid] = $uid;
            }
        }
        if (!$need) {
            return;
        }
        foreach ($need as $uid) {
            self::$user_cache[$uid] = null;
        }
        $query = $db->simple_select('users', 'uid, username, usergroup, displaygroup', 'uid IN (' . implode(',', $need) . ')');
        while ($row = $db->fetch_array($query)) {
            self::$user_cache[(int)$row['uid']] = [
                'username'     => (string)$row['username'],
                'usergroup'    => (int)$row['usergroup'],
                'displaygroup' => (int)$row['displaygroup'],
            ];
        }
    }

    /** Name + Gruppen eines Accounts (null = existiert nicht). */
    public static function get_user(int $uid): ?array
    {
        self::load_users([$uid]);
        return self::$user_cache[$uid] ?? null;
    }

    /**
     * Wer hat diese:n Verwandte:n eingetragen, und in welchem Grad? Kommt aus dem
     * optionalen Feld 'relations' von jobliste_resolve_entity_label (nach
     * resolve_labels() im Cache). Leer, wenn der Provider nichts liefert.
     *
     * @return array[] je ['uid' => int, 'relation_label' => string]
     */
    public static function get_relations(int $relative_id): array
    {
        return self::$relations_cache[$relative_id] ?? [];
    }

    /**
     * Charakter-Account, mit dem diese:r Verwandte:r bestaetigt verknuepft ist (0 = keiner
     * oder Account existiert nicht mehr). Kommt aus dem optionalen Feld 'linked_uid' von
     * jobliste_resolve_entity_label (nach resolve_labels() im Cache).
     */
    public static function get_linked_uid(int $relative_id): int
    {
        $uid = self::$linked_cache[$relative_id] ?? 0;
        return ($uid > 0 && self::get_user($uid) !== null) ? $uid : 0;
    }

    /**
     * Eintragende Accounts aller (in diesem Request aufgeloesten) Verwandten, die mit dem
     * Account $uid verknuepft sind -- fuer das Info-Icon an der Account-Zeile. Der Account
     * selbst wird ausgelassen, doppelte uids zusammengefasst.
     *
     * @return array[] je ['uid' => int, 'relation_label' => string]
     */
    public static function get_account_relations(int $uid): array
    {
        $merged = [];
        foreach (self::$linked_cache as $relative_id => $linked_uid) {
            if ($linked_uid !== $uid) {
                continue;
            }
            foreach (self::$relations_cache[$relative_id] ?? [] as $relation) {
                if ($relation['uid'] !== $uid && !isset($merged[$relation['uid']])) {
                    $merged[$relation['uid']] = $relation;
                }
            }
        }
        return array_values($merged);
    }

    /**
     * Relationen fuer das Info-Icon einer Job-Zeile: Verwandte ohne Verknuepfung -> ihre
     * eigenen, Accounts bzw. verknuepfte Verwandte -> die aller mit dem Account
     * verknuepften Verwandten.
     *
     * @return array[] je ['uid' => int, 'relation_label' => string]
     */
    public static function get_relations_for_job(array $job): array
    {
        $id = (int)$job['entity_id'];
        if ($job['entity_type'] === 'user') {
            return self::get_account_relations($id);
        }
        if ($job['entity_type'] !== 'relative') {
            return [];
        }
        $linked_uid = self::get_linked_uid($id);
        return $linked_uid > 0 ? self::get_account_relations($linked_uid) : self::get_relations($id);
    }

    public static function entity_key(string $type, int $id): string
    {
        return $type . ':' . $id;
    }

    /**
     * Entitaeten, fuer die $user Berufe eintragen darf: der eigene Account, (optional)
     * Verwandte ueber jobliste_collect_entities, und fuer Team-Mitglieder alle NPCs.
     *
     * @param array $user MyBB-Userzeile (uid, username, usergroup, additionalgroups)
     * @return array[] je ['entity_type', 'entity_id', 'label', 'relation_label']
     */
    public static function collect_entities(array $user): array
    {
        global $plugins;

        $uid = (int)($user['uid'] ?? 0);
        if ($uid <= 0) {
            return [];
        }
        if (isset(self::$entity_cache[$uid])) {
            return self::$entity_cache[$uid];
        }

        $own = [
            'entity_type'    => 'user',
            'entity_id'      => $uid,
            'label'          => (string)($user['username'] ?? ''),
            'relation_label' => '',
        ];
        $entities = [$own];
        $seen = [self::entity_key('user', $uid) => true];

        if (jobliste_relatives_integration_enabled() && is_object($plugins)) {
            $hook_args = ['uid' => $uid, 'entities' => [$own]];
            $plugins->run_hooks('jobliste_collect_entities', $hook_args);

            $provided = (is_array($hook_args) && isset($hook_args['entities']) && is_array($hook_args['entities']))
                ? $hook_args['entities']
                : [];
            foreach ($provided as $row) {
                $clean = jobliste_sanitize_entity($row, ['relative']);
                if ($clean === null) {
                    continue; // Malformed oder nicht erlaubter Typ: still verwerfen
                }
                $key = self::entity_key($clean['entity_type'], $clean['entity_id']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $entities[] = $clean;
                self::$label_cache[$key] = $clean['label'];
            }
        }

        if (jobliste_is_team($user)) {
            foreach (Jobliste_Database::get_npcs() as $npc) {
                $key = self::entity_key('npc', $npc['id']);
                $entities[] = [
                    'entity_type'    => 'npc',
                    'entity_id'      => $npc['id'],
                    'label'          => $npc['name'],
                    'relation_label' => '',
                ];
                self::$label_cache[$key] = $npc['name'];
            }
        }

        self::$entity_cache[$uid] = $entities;
        return $entities;
    }

    /**
     * Darf $user fuer diese Entitaet Berufe anlegen/aendern (ohne Team-Moderation)?
     *   user     -> nur der Account selbst
     *   relative -> nur wenn die Entitaet ueber jobliste_collect_entities fuer $user
     *               geliefert wurde (Relatives liefert nur eigene Personen)
     *   npc      -> nur Team, und der NPC muss existieren
     */
    public static function can_manage_entity(array $user, string $type, int $entity_id): bool
    {
        $uid = (int)($user['uid'] ?? 0);
        if ($uid <= 0 || $entity_id <= 0) {
            return false;
        }
        switch ($type) {
            case 'user':
                return $entity_id === $uid;
            case 'npc':
                return jobliste_is_team($user) && Jobliste_Database::get_npc($entity_id) !== null;
            case 'relative':
                foreach (self::collect_entities($user) as $entity) {
                    if ($entity['entity_type'] === 'relative' && $entity['entity_id'] === $entity_id) {
                        return true;
                    }
                }
                return false;
        }
        return false;
    }

    /**
     * Darf $user diesen bestehenden Beruf bearbeiten/loeschen? Owner der Entitaet oder
     * Team (Moderation fremder Eintraege).
     */
    public static function can_edit_job(array $user, array $job): bool
    {
        if ((int)($user['uid'] ?? 0) <= 0) {
            return false;
        }
        if (jobliste_is_team($user)) {
            return true;
        }
        return self::can_manage_entity($user, (string)$job['entity_type'], (int)$job['entity_id']);
    }

    /**
     * Ist das Limit jobliste_max_jobs_per_entity fuer diese Entitaet erreicht?
     */
    public static function limit_reached(string $type, int $entity_id): bool
    {
        global $mybb;

        $max = (int)($mybb->settings['jobliste_max_jobs_per_entity'] ?? 0);
        if ($max <= 0) {
            return false;
        }
        return Jobliste_Database::count_jobs_for_entity($type, $entity_id) >= $max;
    }

    /**
     * Anzeigenamen (Rohtext) fuer mehrere Berufe in einem Rutsch. user/npc werden
     * gebuendelt aus den eigenen bzw. Core-Tabellen gelesen, relative ueber
     * jobliste_resolve_entity_label (pro Entitaet einmal). Unaufloesbares ->
     * jobliste_fallback_label().
     *
     * @param array[] $jobs
     * @return array<string, string> "type:id" => Label
     */
    public static function resolve_labels(array $jobs): array
    {
        global $db;

        $need = ['user' => [], 'npc' => [], 'relative' => []];
        foreach ($jobs as $job) {
            $type = (string)$job['entity_type'];
            $id = (int)$job['entity_id'];
            if (!isset($need[$type]) || $id <= 0) {
                continue;
            }
            if (!array_key_exists(self::entity_key($type, $id), self::$label_cache)) {
                $need[$type][$id] = $id;
            }
        }

        if ($need['user']) {
            self::load_users($need['user']);
            foreach ($need['user'] as $id) {
                self::$label_cache[self::entity_key('user', $id)] = self::$user_cache[$id]['username'] ?? null;
            }
        }

        if ($need['npc']) {
            $found = Jobliste_Database::get_npc_names($need['npc']);
            foreach ($need['npc'] as $id) {
                self::$label_cache[self::entity_key('npc', $id)] = $found[$id] ?? null;
            }
        }

        $owner_uids = [];
        foreach ($need['relative'] as $id) {
            self::$label_cache[self::entity_key('relative', $id)] = self::resolve_via_hook('relative', $id);
            foreach (self::$relations_cache[$id] ?? [] as $relation) {
                $owner_uids[] = $relation['uid'];
            }
            if (!empty(self::$linked_cache[$id])) {
                $owner_uids[] = self::$linked_cache[$id];
            }
        }
        // Eintragende und verknuepfte Accounts gleich mitladen (Name + Gruppenfarbe).
        self::load_users($owner_uids);

        $labels = [];
        foreach ($jobs as $job) {
            $key = self::entity_key((string)$job['entity_type'], (int)$job['entity_id']);
            $label = self::$label_cache[$key] ?? null;
            $labels[$key] = ($label === null || $label === '') ? jobliste_fallback_label($job) : $label;
        }
        return $labels;
    }

    /**
     * Konnte die Entitaet dieses Berufs aufgeloest werden (Account/NPC existiert, Relatives
     * kennt die Person)? Erst nach resolve_labels() fuer diesen Beruf aussagekraeftig.
     */
    public static function is_resolved(array $job): bool
    {
        $label = self::$label_cache[self::entity_key((string)$job['entity_type'], (int)$job['entity_id'])] ?? null;
        return $label !== null && $label !== '';
    }

    /**
     * Verwaiste Verwandten-Berufe: Relatives ist aktiv und antwortet, kennt die Person
     * aber nicht mehr (z. B. dort geloescht). Ist Relatives aus/inaktiv, laesst sich das
     * nicht unterscheiden -> [] (nie Berufe nur wegen eines abgeschalteten Plugins als
     * verwaist melden).
     *
     * @return array[]
     */
    public static function find_orphaned_relative_jobs(): array
    {
        if (!jobliste_relatives_provider_active()) {
            return [];
        }
        $jobs = Jobliste_Database::get_jobs_by_type('relative');
        self::resolve_labels($jobs);
        return array_values(array_filter($jobs, static function (array $job): bool {
            return !self::is_resolved($job);
        }));
    }

    /** Einzel-Label (Rohtext) fuer einen Beruf. */
    public static function resolve_label(array $job): string
    {
        $labels = self::resolve_labels([$job]);
        return $labels[self::entity_key((string)$job['entity_type'], (int)$job['entity_id'])];
    }

    /**
     * Feuert jobliste_resolve_entity_label. Liefert null, wenn Integration aus, kein
     * Provider antwortet oder die Antwort kaputt ist.
     */
    private static function resolve_via_hook(string $type, int $id): ?string
    {
        global $plugins;

        self::$relations_cache[$id] = [];
        self::$linked_cache[$id] = 0;
        if (!jobliste_relatives_integration_enabled() || !is_object($plugins)) {
            return null;
        }
        $hook_args = ['entity_type' => $type, 'entity_id' => $id, 'label' => null, 'relations' => [], 'linked_uid' => 0];
        $plugins->run_hooks('jobliste_resolve_entity_label', $hook_args);

        if (!is_array($hook_args)) {
            return null;
        }
        // Optionales Extra: bestaetigt verknuepfter Charakter-Account.
        if (isset($hook_args['linked_uid']) && is_scalar($hook_args['linked_uid'])
            && preg_match('/^\d+$/', (string)$hook_args['linked_uid'])) {
            self::$linked_cache[$id] = (int)$hook_args['linked_uid'];
        }
        // Optionales Extra: wer die Entitaet in welchem Grad eingetragen hat.
        if (isset($hook_args['relations']) && is_array($hook_args['relations'])) {
            self::$relations_cache[$id] = jobliste_sanitize_relations($hook_args['relations']);
        }
        if (!isset($hook_args['label']) || !is_scalar($hook_args['label'])) {
            return null;
        }
        return Jobliste_Database::clean_label((string)$hook_args['label']);
    }
}
