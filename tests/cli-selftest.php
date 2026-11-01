<?php
/**
 * CLI-Selbsttest fuer "jobliste": Kategoriebaum (Zyklenschutz, Sortierung, Loeschen),
 * Entity-Berechtigungen, Sanitize-Vertrag der Provider-Hooks, Fallback-Labels,
 * Limit-Pruefung, Legacy-Pfad-Parsing und die Relatives-Listener (Personendetails sowie
 * Beruf im Personenformular: form_fields/validate/saved, inkl. Schnellformular ohne
 * jobliste_submitted).
 * Legt nur Fixtures mit dem Praefix "SELFTEST" an und raeumt sie am Ende wieder weg.
 *
 * Aufruf (auf dem Server, Plugin installiert):  php tests/cli-selftest.php
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only.');
}
define('IN_MYBB', 1);
$jobliste_root = __DIR__;
while (!file_exists($jobliste_root . "/inc/init.php") && dirname($jobliste_root) !== $jobliste_root) {
    $jobliste_root = dirname($jobliste_root);
}
chdir($jobliste_root);
require_once './inc/init.php';

if (!function_exists('jobliste_info')) {
    require_once MYBB_ROOT . 'inc/plugins/jobliste.php';
}
if (!isset($mybb->user) || !is_array($mybb->user)) {
    $mybb->user = [];
}
$mybb->user += ['uid' => 0, 'usergroup' => 1, 'additionalgroups' => '', 'username' => ''];
$lang->set_path(MYBB_ROOT . 'inc/languages');
$lang->set_language($mybb->settings['bblanguage'] ?? 'english');

$pass = 0;
$fail = 0;
function jobliste_check(string $name, $expected, $actual): void
{
    global $pass, $fail;
    if ($expected === $actual) {
        $pass++;
        echo "  ok   {$name}\n";
    } else {
        $fail++;
        echo "  FAIL {$name}: expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
    }
}

if (!jobliste_is_installed()) {
    echo "jobliste is not installed -- run tests/cli-plugin.php install first.\n";
    exit(1);
}

$created_categories = [];
$created_jobs = [];
$created_npcs = [];

// --- 1. Kategoriebaum -------------------------------------------------------------------

echo "Categories:\n";
Jobliste_Categories::clear_cache();
$a = Jobliste_Categories::create(['name' => 'SELFTEST A', 'parent_id' => 0]);
$b = Jobliste_Categories::create(['name' => 'SELFTEST B', 'parent_id' => $a, 'allow_custom_text' => 1]);
$c = Jobliste_Categories::create(['name' => 'SELFTEST C', 'parent_id' => $b]);
$d = Jobliste_Categories::create(['name' => 'SELFTEST D', 'parent_id' => $a]);
$created_categories = [$c, $d, $b, $a];
jobliste_check('create returns ids', true, $a > 0 && $b > 0 && $c > 0 && $d > 0);
jobliste_check('create rejects empty name', 0, Jobliste_Categories::create(['name' => '   ', 'parent_id' => 0]));
jobliste_check('create rejects unknown parent', 0, Jobliste_Categories::create(['name' => 'SELFTEST X', 'parent_id' => 999999999]));

jobliste_check('path label', 'SELFTEST A › SELFTEST B › SELFTEST C', Jobliste_Categories::get_path_label($c));
jobliste_check('A has children', true, Jobliste_Categories::has_children($a));
jobliste_check('C is leaf', true, Jobliste_Categories::is_leaf($c));
jobliste_check('descendants of A', [$b, $c, $d], (function () use ($a) { $ids = Jobliste_Categories::get_descendant_ids($a); sort($ids); return $ids; })());

jobliste_check('move A under itself -> cycle', Jobliste_Categories::ERR_CYCLE, Jobliste_Categories::update($a, ['parent_id' => $a]));
jobliste_check('move A under grandchild C -> cycle', Jobliste_Categories::ERR_CYCLE, Jobliste_Categories::update($a, ['parent_id' => $c]));
jobliste_check('move B under own child C -> cycle', Jobliste_Categories::ERR_CYCLE, Jobliste_Categories::update($b, ['parent_id' => $c]));
jobliste_check('A parent unchanged after rejected move', 0, Jobliste_Categories::get($a)['parent_id']);
jobliste_check('move C under D -> allowed', '', Jobliste_Categories::update($c, ['parent_id' => $d]));
jobliste_check('C now below D', 'SELFTEST A › SELFTEST D › SELFTEST C', Jobliste_Categories::get_path_label($c));
jobliste_check('move D under B -> allowed (C follows)', '', Jobliste_Categories::update($d, ['parent_id' => $b]));
jobliste_check('C path after moving D', 'SELFTEST A › SELFTEST B › SELFTEST D › SELFTEST C', Jobliste_Categories::get_path_label($c));
jobliste_check('move to unknown parent rejected', Jobliste_Categories::ERR_PARENT, Jobliste_Categories::update($c, ['parent_id' => 999999999]));
jobliste_check('rename to empty rejected', Jobliste_Categories::ERR_NAME, Jobliste_Categories::update($c, ['name' => '']));

// Sortierung: E und F unter A, F nach oben
$e = Jobliste_Categories::create(['name' => 'SELFTEST E', 'parent_id' => $a]);
$f = Jobliste_Categories::create(['name' => 'SELFTEST F', 'parent_id' => $a]);
array_unshift($created_categories, $e, $f);
$order = Jobliste_Categories::get_children_map()[$a];
jobliste_check('initial sibling order', [$b, $e, $f], $order);
jobliste_check('move F up', true, Jobliste_Categories::move($f, 'up'));
jobliste_check('sibling order after move up', [$b, $f, $e], Jobliste_Categories::get_children_map()[$a]);
jobliste_check('move first sibling up is a no-op', false, Jobliste_Categories::move($b, 'up'));
jobliste_check('move E down (last) is a no-op', false, Jobliste_Categories::move($e, 'down'));

$flat = array_values(array_filter(Jobliste_Categories::get_flat_tree(), static function ($row) use ($a, $b, $c, $d, $e, $f) {
    return in_array($row['id'], [$a, $b, $c, $d, $e, $f], true);
}));
jobliste_check('flat tree depth-first order', [$a, $b, $d, $c, $f, $e], array_column($flat, 'id'));
jobliste_check('flat tree depths', [0, 1, 2, 3, 1, 1], array_column($flat, 'depth'));

Jobliste_Categories::update($e, ['is_active' => 0]);
$active_ids = array_column(Jobliste_Categories::get_flat_tree(true), 'id');
jobliste_check('inactive category hidden from active tree', false, in_array($e, $active_ids, true));

jobliste_check('delete category with children rejected', Jobliste_Categories::ERR_HAS_CHILDREN, Jobliste_Categories::delete($b));

// --- 2. Jobs + Berechtigungen -------------------------------------------------------------

echo "Jobs & permissions:\n";

// Fixture-User: Besitzer*in mit Relatives-Personen, falls Relatives installiert ist.
$owner_uid = 0;
$own_person = 0;
$foreign_person = 0;
$relatives_available = $db->table_exists('relatives_person_owners') && isset($plugins->hooks['jobliste_collect_entities']);
if ($relatives_available) {
    $row = $db->fetch_array($db->query("SELECT o.owner_uid, MIN(o.person_id) AS person_id
        FROM " . TABLE_PREFIX . "relatives_person_owners o
        JOIN " . TABLE_PREFIX . "users u ON u.uid = o.owner_uid
        GROUP BY o.owner_uid ORDER BY COUNT(*) DESC LIMIT 1"));
    if ($row) {
        $owner_uid = (int)$row['owner_uid'];
        $own_person = (int)$row['person_id'];
        $foreign = $db->fetch_array($db->query("SELECT p.id FROM " . TABLE_PREFIX . "relatives_persons p
            WHERE NOT EXISTS (SELECT 1 FROM " . TABLE_PREFIX . "relatives_person_owners o WHERE o.person_id = p.id AND o.owner_uid = " . $owner_uid . ")
            LIMIT 1"));
        $foreign_person = $foreign ? (int)$foreign['id'] : 0;
    }
}
if ($owner_uid <= 0) {
    $owner_uid = (int)$db->fetch_field($db->simple_select('users', 'uid', '', ['order_by' => 'uid', 'limit' => 1]), 'uid');
}
$owner = get_user($owner_uid);
$owner['usergroup'] = 2;
$owner['additionalgroups'] = '';

$other_uid = (int)$db->fetch_field($db->simple_select('users', 'uid', 'uid != ' . $owner_uid, ['order_by' => 'uid', 'limit' => 1]), 'uid');
$team = get_user($other_uid);
$team['usergroup'] = 4;
$team['additionalgroups'] = '';
$non_team = $team;
$non_team['usergroup'] = 2;

$mybb->settings['jobliste_team_usergroups'] = '4';
$mybb->settings['jobliste_relatives_integration'] = 1;
$mybb->settings['jobliste_max_jobs_per_entity'] = 0;

jobliste_check('team detection (group 4)', true, jobliste_is_team($team));
jobliste_check('non-team detection', false, jobliste_is_team($non_team));
$mybb->settings['jobliste_team_usergroups'] = '';
jobliste_check('empty team setting -> nobody is team', false, jobliste_is_team($team));
$mybb->settings['jobliste_team_usergroups'] = '3,4';

jobliste_check('user may manage own account', true, Jobliste_EntityResolver::can_manage_entity($owner, 'user', $owner_uid));
jobliste_check('user may not manage foreign account', false, Jobliste_EntityResolver::can_manage_entity($owner, 'user', $other_uid));
jobliste_check('unknown entity type rejected', false, Jobliste_EntityResolver::can_manage_entity($owner, 'guild', 1));

if ($relatives_available && $own_person > 0) {
    Jobliste_EntityResolver::clear_cache();
    jobliste_check('relative owned by user -> allowed', true, Jobliste_EntityResolver::can_manage_entity($owner, 'relative', $own_person));
    if ($foreign_person > 0) {
        jobliste_check('relative NOT owned by user -> rejected', false, Jobliste_EntityResolver::can_manage_entity($owner, 'relative', $foreign_person));
    }
    $mybb->settings['jobliste_relatives_integration'] = 0;
    Jobliste_EntityResolver::clear_cache();
    jobliste_check('integration off -> own relative rejected', false, Jobliste_EntityResolver::can_manage_entity($owner, 'relative', $own_person));
    jobliste_check('integration off -> no relatives collected', [], array_values(array_filter(Jobliste_EntityResolver::collect_entities($owner), static function ($e) {
        return $e['entity_type'] === 'relative';
    })));
    $mybb->settings['jobliste_relatives_integration'] = 1;
    Jobliste_EntityResolver::clear_cache();
} else {
    echo "  skip relatives permission tests (relatives plugin not active/installed)\n";
}

$npc_id = Jobliste_Database::insert_npc('SELFTEST NPC', 'notes', (int)$team['uid']);
$created_npcs[] = $npc_id;
jobliste_check('npc created', true, $npc_id > 0);
jobliste_check('team may manage NPC', true, Jobliste_EntityResolver::can_manage_entity($team, 'npc', $npc_id));
jobliste_check('non-team may not manage NPC', false, Jobliste_EntityResolver::can_manage_entity($non_team, 'npc', $npc_id));
jobliste_check('team may not manage unknown NPC', false, Jobliste_EntityResolver::can_manage_entity($team, 'npc', 999999999));
Jobliste_EntityResolver::clear_cache();
jobliste_check('team collects NPC entities', true, in_array('npc:' . $npc_id, array_map(static function ($e) {
    return $e['entity_type'] . ':' . $e['entity_id'];
}, Jobliste_EntityResolver::collect_entities($team)), true));

$job_id = Jobliste_Database::insert_job(['entity_type' => 'user', 'entity_id' => $owner_uid, 'category_id' => $c, 'custom_label' => 'SELFTEST job', 'created_by_uid' => $owner_uid]);
$created_jobs[] = $job_id;
jobliste_check('job inserted', true, $job_id > 0);
jobliste_check('job without category and label rejected', 0, Jobliste_Database::insert_job(['entity_type' => 'user', 'entity_id' => $owner_uid, 'category_id' => null, 'custom_label' => '  ']));
jobliste_check('job with invalid type rejected', 0, Jobliste_Database::insert_job(['entity_type' => 'guild', 'entity_id' => 1, 'category_id' => $c]));
$job = Jobliste_Database::get_job($job_id);
jobliste_check('owner may edit own job', true, Jobliste_EntityResolver::can_edit_job($owner, $job));
jobliste_check('non-team stranger may not edit', false, Jobliste_EntityResolver::can_edit_job($non_team, $job));
jobliste_check('team may moderate foreign job', true, Jobliste_EntityResolver::can_edit_job($team, $job));

jobliste_check('update job', true, Jobliste_Database::update_job($job_id, $d, null));
$job = Jobliste_Database::get_job($job_id);
jobliste_check('updated category', $d, $job['category_id']);
jobliste_check('updated label cleared', null, $job['custom_label']);

$mybb->settings['jobliste_max_jobs_per_entity'] = 1;
jobliste_check('limit reached at 1', true, Jobliste_EntityResolver::limit_reached('user', $owner_uid));
$mybb->settings['jobliste_max_jobs_per_entity'] = 0;
jobliste_check('limit 0 = unlimited', false, Jobliste_EntityResolver::limit_reached('user', $owner_uid));

// Kategorie loeschen: Job wandert zur Elternkategorie
$moved = 0;
jobliste_check('delete leaf C allowed', '', Jobliste_Categories::delete($c, $moved));
$created_categories = array_values(array_diff($created_categories, [$c]));
$job_on_d = Jobliste_Database::insert_job(['entity_type' => 'npc', 'entity_id' => $npc_id, 'category_id' => $d, 'created_by_uid' => (int)$team['uid']]);
$created_jobs[] = $job_on_d;
$moved = 0;
jobliste_check('delete D with jobs allowed', '', Jobliste_Categories::delete($d, $moved));
$created_categories = array_values(array_diff($created_categories, [$d]));
jobliste_check('two jobs moved on delete', 2, $moved);
jobliste_check('job moved to parent B', $b, Jobliste_Database::get_job($job_on_d)['category_id']);

// Loeschen eines Users (Hook-Handler): eigene Jobs weg, created_by freigegeben
$fake_handler = new stdClass();
$fake_handler->delete_uids = [(int)$team['uid']];
$created_by_before = Jobliste_Database::get_job($job_on_d)['created_by_uid'];
jobliste_handle_user_delete($fake_handler);
jobliste_check('created_by released on user delete', [(int)$team['uid'], 0], [$created_by_before, Jobliste_Database::get_job($job_on_d)['created_by_uid']]);
jobliste_check('NPC job kept on user delete', true, Jobliste_Database::get_job($job_on_d) !== null);

// --- 3. Provider-Vertrag -------------------------------------------------------------------

echo "Provider contract:\n";
jobliste_check('sanitize valid row', ['entity_type' => 'relative', 'entity_id' => 5, 'label' => 'Anna', 'relation_label' => 'Mutter'],
    jobliste_sanitize_entity(['entity_type' => 'relative', 'entity_id' => '5', 'label' => ' Anna ', 'relation_label' => 'Mutter']));
jobliste_check('sanitize rejects non-array', null, jobliste_sanitize_entity('relative:5'));
jobliste_check('sanitize rejects user type from provider', null, jobliste_sanitize_entity(['entity_type' => 'user', 'entity_id' => 1, 'label' => 'x']));
jobliste_check('sanitize rejects negative id', null, jobliste_sanitize_entity(['entity_type' => 'relative', 'entity_id' => -3, 'label' => 'x']));
jobliste_check('sanitize rejects non-numeric id', null, jobliste_sanitize_entity(['entity_type' => 'relative', 'entity_id' => '5 OR 1=1', 'label' => 'x']));
jobliste_check('sanitize rejects empty label', null, jobliste_sanitize_entity(['entity_type' => 'relative', 'entity_id' => 5, 'label' => '   ']));
jobliste_check('sanitize rejects array label', null, jobliste_sanitize_entity(['entity_type' => 'relative', 'entity_id' => 5, 'label' => ['x']]));
jobliste_check('sanitize keeps raw text (escaping is done on render)', '<b>X</b>', jobliste_sanitize_entity(['entity_type' => 'relative', 'entity_id' => 5, 'label' => '<b>X</b>'])['label']);

// Kaputter Wegwerf-Provider (nur in diesem Prozess registriert)
function jobliste_selftest_broken_provider(&$args): void
{
    $args['entities'][] = 'garbage';
    $args['entities'][] = ['entity_type' => 'user', 'entity_id' => 1, 'label' => 'hijack'];
    $args['entities'][] = ['entity_type' => 'relative', 'entity_id' => 0, 'label' => 'zero'];
    $args['entities'][] = ['entity_type' => 'relative', 'entity_id' => 987654321, 'label' => 'SELFTEST valid'];
}
function jobliste_selftest_label_provider(&$args): void
{
    if ($args['entity_type'] === 'relative' && $args['entity_id'] === 987654321) {
        $args['label'] = ['broken'];
    }
}
$plugins->add_hook('jobliste_collect_entities', 'jobliste_selftest_broken_provider', 50);
$plugins->add_hook('jobliste_resolve_entity_label', 'jobliste_selftest_label_provider', 50);
Jobliste_EntityResolver::clear_cache();
$keys = array_map(static function ($e) {
    return $e['entity_type'] . ':' . $e['entity_id'];
}, Jobliste_EntityResolver::collect_entities($owner));
jobliste_check('broken provider: own account first', 'user:' . $owner_uid, $keys[0]);
jobliste_check('broken provider: hijack user row dropped', 1, count(array_filter($keys, static function ($k) {
    return strpos($k, 'user:') === 0;
})));
jobliste_check('broken provider: valid row kept', true, in_array('relative:987654321', $keys, true));
jobliste_check('broken provider: zero id dropped', false, in_array('relative:0', $keys, true));

Jobliste_EntityResolver::clear_cache();
$fake_relative_job = ['entity_type' => 'relative', 'entity_id' => 987654321];
jobliste_check('broken label provider -> fallback label', jobliste_fallback_label($fake_relative_job), Jobliste_EntityResolver::resolve_label($fake_relative_job));
$mybb->settings['jobliste_relatives_integration'] = 0;
Jobliste_EntityResolver::clear_cache();
jobliste_check('integration off -> fallback label', jobliste_fallback_label($fake_relative_job), Jobliste_EntityResolver::resolve_label($fake_relative_job));
$mybb->settings['jobliste_relatives_integration'] = 1;
if ($relatives_available && $own_person > 0) {
    Jobliste_EntityResolver::clear_cache();
    $person_name = (string)$db->fetch_field($db->simple_select('relatives_persons', 'name', 'id = ' . $own_person), 'name');
    jobliste_check('relatives provider resolves label', $person_name, Jobliste_EntityResolver::resolve_label(['entity_type' => 'relative', 'entity_id' => $own_person]));
}
// Verwaiste Verwandten-Berufe: Person unbekannt -> nicht aufgeloest; als verwaist gemeldet
// nur, wenn Relatives wirklich antwortet.
Jobliste_EntityResolver::clear_cache();
$orphan_job_id = Jobliste_Database::insert_job(['entity_type' => 'relative', 'entity_id' => 987654322, 'custom_label' => 'SELFTEST Waise']);
$created_jobs[] = $orphan_job_id;
$orphan_job = Jobliste_Database::get_job($orphan_job_id);
Jobliste_EntityResolver::resolve_labels([$orphan_job]);
jobliste_check('unknown relative -> not resolved', false, Jobliste_EntityResolver::is_resolved($orphan_job));
$orphan_ids = array_column(Jobliste_EntityResolver::find_orphaned_relative_jobs(), 'id');
jobliste_check('orphan detected only with active provider', jobliste_relatives_provider_active(), in_array($orphan_job_id, $orphan_ids, true));
if ($relatives_available && $own_person > 0) {
    $own_relative_job = ['entity_type' => 'relative', 'entity_id' => $own_person];
    Jobliste_EntityResolver::resolve_labels([$own_relative_job]);
    jobliste_check('existing relative is resolved (no orphan)', true, Jobliste_EntityResolver::is_resolved($own_relative_job));
}
$mybb->settings['jobliste_relatives_integration'] = 0;
Jobliste_EntityResolver::clear_cache();
jobliste_check('integration off -> never reports orphans', [], Jobliste_EntityResolver::find_orphaned_relative_jobs());
$mybb->settings['jobliste_relatives_integration'] = 1;
Jobliste_EntityResolver::clear_cache();
jobliste_check('deleted account -> fallback label', jobliste_fallback_label(['entity_type' => 'user']), Jobliste_EntityResolver::resolve_label(['entity_type' => 'user', 'entity_id' => 999999999]));
jobliste_check('NPC label resolved', 'SELFTEST NPC', Jobliste_EntityResolver::resolve_label(['entity_type' => 'npc', 'entity_id' => $npc_id]));

// --- 4. Kategorietyp "Ebenen" -------------------------------------------------------------

echo "Levels:\n";
jobliste_check('profilefield options (select, "--" dropped)', ['GB', 'Frankreich', 'Irland'], Jobliste_Levels::parse_profilefield_options("select\n--\nGB\nFrankreich\n\nIrland\r\n"));
jobliste_check('profilefield options of text field', [], Jobliste_Levels::parse_profilefield_options("text"));
jobliste_check('list parsing trims + dedupes', ['Anderes', 'Bildung'], Jobliste_Levels::parse_list(" Anderes \n\nBildung\nanderes\n"));
jobliste_check('encode/decode roundtrip (unicode)', ['Dänemark', 'Dienstleistungssektor'], Jobliste_Levels::decode_values(Jobliste_Levels::encode_values(['Dänemark', ' Dienstleistungssektor '])));
jobliste_check('encode empty -> null', null, Jobliste_Levels::encode_values([' ', '']));
jobliste_check('decode garbage -> []', [], Jobliste_Levels::decode_values('{"a":'));

$lv = Jobliste_Categories::create(['name' => 'SELFTEST Muggel', 'parent_id' => 0, 'type' => 'levels', 'allow_custom_text' => 1]);
$tree_parent = Jobliste_Categories::create(['name' => 'SELFTEST Baum', 'parent_id' => 0]);
$tree_child = Jobliste_Categories::create(['name' => 'SELFTEST Blatt', 'parent_id' => $tree_parent]);
array_unshift($created_categories, $tree_child, $tree_parent, $lv);
jobliste_check('levels category created', 'levels', Jobliste_Categories::get($lv)['type']);
jobliste_check('no child under levels category', 0, Jobliste_Categories::create(['name' => 'SELFTEST X', 'parent_id' => $lv]));
jobliste_check('no move under levels category', Jobliste_Categories::ERR_PARENT_LEVELS, Jobliste_Categories::update($tree_child, ['parent_id' => $lv]));
jobliste_check('category with children cannot become levels', Jobliste_Categories::ERR_LEVELS_HAS_CHILDREN, Jobliste_Categories::update($tree_parent, ['type' => 'levels']));
jobliste_check('leaf may become levels', '', Jobliste_Categories::update($tree_child, ['type' => 'levels']));

$level_country = Jobliste_Levels::create($lv, ['label' => 'Land', 'source' => 'list', 'options' => "GB\nFrankreich"]);
$level_sector = Jobliste_Levels::create($lv, ['label' => 'Sektor', 'source' => 'list', 'options' => "Anderes\nBildung"]);
jobliste_check('levels created', true, $level_country > 0 && $level_sector > 0);
jobliste_check('level with unknown profilefield rejected', 0, Jobliste_Levels::create($lv, ['label' => 'X', 'source' => 'profilefield', 'profilefield_fid' => 999999]));
jobliste_check('level without label rejected', 0, Jobliste_Levels::create($lv, ['label' => ' ', 'source' => 'list']));
jobliste_check('levels in order', ['Land', 'Sektor'], array_column(Jobliste_Levels::get_levels($lv), 'label'));
jobliste_check('validate ok', [true, ['GB', 'Anderes']], Jobliste_Levels::validate_values($lv, ['GB', 'Anderes']));
jobliste_check('validate unknown option', [false, []], Jobliste_Levels::validate_values($lv, ['Atlantis', 'Anderes']));
jobliste_check('validate missing level', [false, []], Jobliste_Levels::validate_values($lv, ['GB']));
jobliste_check('validate non-array', [false, []], Jobliste_Levels::validate_values($lv, 'GB'));
jobliste_check('edit keeps a legacy value', [true, ['Wales', 'Anderes']], Jobliste_Levels::validate_values($lv, ['Wales', 'Anderes'], ['Wales', 'Bildung']));
jobliste_check('level move down', true, Jobliste_Levels::move($level_country, 'down'));
jobliste_check('levels order after move', ['Sektor', 'Land'], array_column(Jobliste_Levels::get_levels($lv), 'label'));
Jobliste_Levels::add_list_options($level_sector, ['Dienstleistungssektor', 'anderes']);
jobliste_check('add_list_options appends without dupes', ['Anderes', 'Bildung', 'Dienstleistungssektor'], Jobliste_Levels::get_options(Jobliste_Levels::get_level($level_sector)));

$profilefields = Jobliste_Levels::get_selectable_profilefields();
if ($profilefields) {
    $fid = (int)array_key_first($profilefields);
    $level_pf = Jobliste_Levels::create($tree_child, ['label' => 'SELFTEST PF', 'source' => 'profilefield', 'profilefield_fid' => $fid]);
    $pf_options = Jobliste_Levels::get_options(Jobliste_Levels::get_level($level_pf));
    jobliste_check('profilefield level has options', true, count($pf_options) > 0);
    jobliste_check('profilefield option validates', true, Jobliste_Levels::validate_values($tree_child, [$pf_options[0] ?? ''])[0]);
}

$level_job = Jobliste_Database::insert_job(['entity_type' => 'npc', 'entity_id' => 999999, 'category_id' => $lv, 'custom_label' => 'SELFTEST Arzt', 'level_values' => ['GB', 'Anderes']]);
$created_jobs[] = $level_job;
jobliste_check('job with level values stored', ['GB', 'Anderes'], Jobliste_Database::get_job($level_job)['level_values']);
Jobliste_Database::update_job($level_job, $lv, 'SELFTEST Arzt', ['Frankreich', 'Bildung']);
jobliste_check('job level values updated', ['Frankreich', 'Bildung'], Jobliste_Database::get_job($level_job)['level_values']);
Jobliste_Categories::delete($lv);
$created_categories = array_values(array_diff($created_categories, [$lv]));
jobliste_check('levels removed with category', [], Jobliste_Levels::get_levels($lv));

// --- 5. Legacy-Pfad-Parsing ----------------------------------------------------------------

echo "Legacy path parsing:\n";
jobliste_check('parse GB path', ['ids' => [1, 43, 46, 47], 'texts' => ['Sekretärin']], Jobliste_Migration::parse_legacy_path("1\n43\n46\n47\nSekretärin\n"));
jobliste_check('parse numeric-only path', ['ids' => [1, 3, 109], 'texts' => []], Jobliste_Migration::parse_legacy_path("1\n3\n109\n"));
jobliste_check('parse Muggel path', ['ids' => [7], 'texts' => ['GB', 'Anderes', 'Militärarzt']], Jobliste_Migration::parse_legacy_path("7\r\nGB\r\nAnderes\r\nMilitärarzt\r\n"));
jobliste_check('parse "keine Angabe"', ['ids' => [0], 'texts' => []], Jobliste_Migration::parse_legacy_path("0\n"));
jobliste_check('parse empty', ['ids' => [], 'texts' => []], Jobliste_Migration::parse_legacy_path(''));
jobliste_check('digits after text stay text', ['ids' => [1], 'texts' => ['Jahrgang', '2020']], Jobliste_Migration::parse_legacy_path("1\nJahrgang\n2020\n"));

$sample_values = ["1\n43\n46\n47\nSekretärin\n", "7\nGB\nAnderes\nMilitärarzt\n", "2\nFrankreich\nBildung\nSchülerin\n", "2\nAustralien\n", "0\n", "1\n3\n109\n"];
jobliste_check('detect level roots', [2, 7], Jobliste_Migration::detect_level_roots($sample_values));

// Echte Beispieldaten des Altplugins (Kategorienamen, keine Personendaten)
$fixture = __DIR__ . '/fixtures/mybb_relatives_items.sql';
if (is_file($fixture)) {
    preg_match_all("/\((\d+),\s*'((?:[^'\\\\]|\\\\.)*)',\s*(\d+),\s*(\d+)\)/u", (string)file_get_contents($fixture), $matches, PREG_SET_ORDER);
    $items = [];
    foreach ($matches as $m) {
        $items[(int)$m[1]] = ['name' => $m[2], 'parent' => (int)$m[3], 'next' => (int)$m[4]];
    }
    jobliste_check('fixture: all items parsed', 110, count($items));
    $orphans = array_filter($items, static function ($item) use (&$items) {
        return $item['parent'] !== 0 && !isset($items[$item['parent']]);
    });
    jobliste_check('fixture: no orphan parents', [], array_keys($orphans));

    // Fiktive Zuordnung legacy -> neu (id + 1000) und fuer jedes Item den Altpfad bauen
    $map = [];
    foreach ($items as $id => $item) {
        $map[$id] = $id + 1000;
    }
    $all_ok = true;
    foreach ($items as $id => $item) {
        $chain = [];
        for ($cur = $id; $cur > 0 && count($chain) < 20; $cur = $items[$cur]['parent']) {
            array_unshift($chain, $cur);
        }
        $value = implode("\n", $chain) . "\n" . ($item['next'] === 2 ? "SELFTEST Text\n" : '');
        $resolved = Jobliste_Migration::resolve_path(Jobliste_Migration::parse_legacy_path($value), $map, [2, 7]);
        $expected_label = $item['next'] === 2 ? 'SELFTEST Text' : null;
        if ($resolved['category_id'] !== $id + 1000 || $resolved['custom_label'] !== $expected_label || $resolved['unresolved_ids']) {
            $all_ok = false;
            echo "    mismatch for item {$id}: " . json_encode($resolved) . "\n";
        }
    }
    jobliste_check('fixture: every legacy path resolves to its deepest item', true, $all_ok);

    $r = Jobliste_Migration::resolve_path(Jobliste_Migration::parse_legacy_path("1\n43\n46\n47\nSekretärin\n"), $map, [2, 7]);
    jobliste_check('resolve GB path', [1047, [], 'Sekretärin'], [$r['category_id'], $r['level_values'], $r['custom_label']]);
    $r = Jobliste_Migration::resolve_path(Jobliste_Migration::parse_legacy_path("7\nGB\nAnderes\nMilitärarzt\n"), $map, [2, 7]);
    jobliste_check('resolve Muggel path -> levels', [1007, ['GB', 'Anderes'], 'Militärarzt'], [$r['category_id'], $r['level_values'], $r['custom_label']]);
    $r = Jobliste_Migration::resolve_path(Jobliste_Migration::parse_legacy_path("2\nIrland\nSonstiges\nTreiber\nKenmare\n"), $map, [2, 7]);
    jobliste_check('resolve extra text segments joined', [1002, ['Irland', 'Sonstiges'], 'Treiber – Kenmare'], [$r['category_id'], $r['level_values'], $r['custom_label']]);
    $r = Jobliste_Migration::resolve_path(Jobliste_Migration::parse_legacy_path("2\nAustralien\n"), $map, [2, 7]);
    jobliste_check('resolve partial levels', [1002, ['Australien'], null, false], [$r['category_id'], $r['level_values'], $r['custom_label'], $r['skip']]);
    $r = Jobliste_Migration::resolve_path(Jobliste_Migration::parse_legacy_path("0\n"), $map, [2, 7]);
    jobliste_check('resolve "keine Angabe" -> skip', true, $r['skip']);
    $r = Jobliste_Migration::resolve_path(Jobliste_Migration::parse_legacy_path("1\n999\nX\n"), $map, [2, 7]);
    jobliste_check('resolve unknown id reported', [1001, [999], 'X'], [$r['category_id'], $r['unresolved_ids'], $r['custom_label']]);
} else {
    echo "  skip fixture tests (tests/fixtures/mybb_relatives_items.sql not found)\n";
}

// --- Kategorie-Antraege --------------------------------------------------------------------

echo "Category requests:\n";
Jobliste_Categories::clear_cache();
$req_uid = 999999990; // Fixture-uid, Antraege pruefen den Account nicht
$req_root = Jobliste_Categories::create(['name' => 'SELFTEST REQ ROOT', 'parent_id' => 0]);
$req_levels = Jobliste_Categories::create(['name' => 'SELFTEST REQ LEVELS', 'parent_id' => 0, 'type' => 'levels']);
$req_find_id = function (string $name) use ($db): int {
    return (int)$db->fetch_field($db->simple_select('jobliste_requests', 'id', "name = '" . $db->escape_string($name) . "'", ['order_by' => 'id', 'order_dir' => 'DESC', 'limit' => 1]), 'id');
};
jobliste_check('request: empty name rejected', Jobliste_Requests::ERR_NAME, Jobliste_Requests::create($req_uid, '  ', $req_root, ''));
jobliste_check('request: unknown parent rejected', Jobliste_Requests::ERR_PARENT, Jobliste_Requests::create($req_uid, 'SELFTEST R1', 999999999, ''));
jobliste_check('request: levels parent rejected', Jobliste_Requests::ERR_PARENT, Jobliste_Requests::create($req_uid, 'SELFTEST R1', $req_levels, ''));
jobliste_check('request: existing category (case-insensitive) rejected', Jobliste_Requests::ERR_EXISTS, Jobliste_Requests::create($req_uid, 'selftest req root', 0, ''));
jobliste_check('request: valid request saved', '', Jobliste_Requests::create($req_uid, 'SELFTEST R1', $req_root, 'Grund'));
jobliste_check('request: duplicate pending request rejected', Jobliste_Requests::ERR_DUPLICATE, Jobliste_Requests::create($req_uid + 1, 'SELFTEST r1', $req_root, ''));
jobliste_check('request: pending count per user', 1, Jobliste_Requests::count_pending($req_uid));
for ($i = 2; $i <= Jobliste_Requests::MAX_PENDING_PER_USER; $i++) {
    Jobliste_Requests::create($req_uid, 'SELFTEST R' . $i, $req_root, '');
}
jobliste_check('request: limit per user', Jobliste_Requests::ERR_LIMIT, Jobliste_Requests::create($req_uid, 'SELFTEST R99', $req_root, ''));

$r1 = $req_find_id('SELFTEST R1');
$r2 = $req_find_id('SELFTEST R2');
$r3 = $req_find_id('SELFTEST R3');
jobliste_check('request: foreign user cannot withdraw', false, Jobliste_Requests::withdraw($r3, $req_uid + 1));
jobliste_check('request: owner withdraws', true, Jobliste_Requests::withdraw($r3, $req_uid));
jobliste_check('request: withdrawn request gone', null, Jobliste_Requests::get($r3));

$new_cat = 0;
jobliste_check('approve: empty name rejected', Jobliste_Requests::ERR_NAME, Jobliste_Requests::approve($r1, '', $req_root, false, '', 1, $new_cat));
jobliste_check('approve: renamed + custom text', '', Jobliste_Requests::approve($r1, 'SELFTEST R1 renamed', $req_root, true, 'passt', 1, $new_cat));
$approved = Jobliste_Requests::get($r1);
jobliste_check('approve: status + category linked', [Jobliste_Requests::STATUS_APPROVED, $new_cat], [$approved['status'], $approved['category_id']]);
jobliste_check('approve: category created below parent', 'SELFTEST REQ ROOT › SELFTEST R1 renamed', Jobliste_Categories::get_path_label($new_cat));
jobliste_check('approve: allow_custom_text set', 1, Jobliste_Categories::get($new_cat)['allow_custom_text']);
jobliste_check('approve: second decision rejected', Jobliste_Requests::ERR_NOT_PENDING, Jobliste_Requests::reject($r1, '', 1));
jobliste_check('reject: saved with response', '', Jobliste_Requests::reject($r2, 'doppelt', 1));
jobliste_check('reject: status + response', [Jobliste_Requests::STATUS_REJECTED, 'doppelt'], [Jobliste_Requests::get($r2)['status'], Jobliste_Requests::get($r2)['response']]);
jobliste_check('approve after reject rejected', Jobliste_Requests::ERR_NOT_PENDING, Jobliste_Requests::approve($r2, 'SELFTEST R2', 0, false, '', 1));

Jobliste_Requests::handle_user_delete($req_uid);
jobliste_check('user delete removes own requests', 0, count(Jobliste_Requests::get_by_user($req_uid)));
$db->delete_query('jobliste_requests', "name LIKE 'SELFTEST%'");

// --- Listener relatives_collect_person_details ---------------------------------------------

echo "Relatives person details:\n";
jobliste_check('job text: levels + custom label', 'GB' . Jobliste_Categories::PATH_SEPARATOR . 'Anderes – Arzt',
    jobliste_job_text(['category_id' => null, 'level_values' => ['GB', 'Anderes'], 'custom_label' => 'Arzt']));
jobliste_check('job text: empty job -> empty string', '', jobliste_job_text(['category_id' => null, 'level_values' => [], 'custom_label' => null]));

Jobliste_Categories::clear_cache();
$pd_cat = Jobliste_Categories::create(['name' => 'SELFTEST PD', 'parent_id' => 0, 'allow_custom_text' => 1]);
$pd_uid = 999999980;        // Fixture-Account (gespielter Charakter)
$pd_played = 999999981;     // Person, bestaetigt verknuepft mit $pd_uid
$pd_npc = 999999982;        // Person ohne Account
$pd_played_too = 999999983; // zweite Person mit demselben Account
$pd_empty = 999999984;      // Person ohne Beruf
foreach ([
    ['entity_type' => 'user', 'entity_id' => $pd_uid, 'category_id' => $pd_cat, 'custom_label' => 'SELFTEST Lehrer'],
    ['entity_type' => 'user', 'entity_id' => $pd_uid, 'category_id' => null, 'custom_label' => 'SELFTEST Zweitjob'],
    ['entity_type' => 'relative', 'entity_id' => $pd_npc, 'category_id' => $pd_cat, 'custom_label' => 'SELFTEST <b>Heiler</b>'],
    // Relative-Beruf einer verknuepften Person: wird ignoriert, es zaehlt der Account
    ['entity_type' => 'relative', 'entity_id' => $pd_played, 'category_id' => null, 'custom_label' => 'SELFTEST ignoriert'],
] as $pd_job) {
    $created_jobs[] = Jobliste_Database::insert_job($pd_job);
}

$pd_args = [
    'person_ids'  => [$pd_played, $pd_npc, $pd_played_too, $pd_empty, 'x', -1],
    'linked_uids' => [$pd_played => $pd_uid, $pd_played_too => $pd_uid],
    'details'     => [$pd_empty => ['other' => 'kept']],
];
jobliste_provide_relatives_person_details($pd_args);
$pd_user_text = 'SELFTEST PD – SELFTEST Lehrer, SELFTEST Zweitjob';
jobliste_check('played character -> account jobs joined by id', $pd_user_text, $pd_args['details'][$pd_played]['job'] ?? null);
jobliste_check('second person on same account', $pd_user_text, $pd_args['details'][$pd_played_too]['job'] ?? null);
jobliste_check('NPC person -> relative job, raw text', 'SELFTEST PD – SELFTEST <b>Heiler</b>', $pd_args['details'][$pd_npc]['job'] ?? null);
jobliste_check('person without job -> no job key, other details kept', ['other' => 'kept'], $pd_args['details'][$pd_empty]);
jobliste_check('no entries for invalid ids', [$pd_played, $pd_npc, $pd_played_too, $pd_empty], (function () use ($pd_args) { $k = array_keys($pd_args['details']); sort($k); return $k; })());

// Ueber den echten Hook (Registrierung pruefen)
$pd_args = ['person_ids' => [$pd_npc], 'linked_uids' => [], 'details' => []];
$plugins->run_hooks('relatives_collect_person_details', $pd_args);
jobliste_check('hook registered and fired', 'SELFTEST PD – SELFTEST <b>Heiler</b>', $pd_args['details'][$pd_npc]['job'] ?? null);

$mybb->settings['jobliste_relatives_integration'] = 0;
$pd_args = ['person_ids' => [$pd_npc], 'linked_uids' => [], 'details' => []];
jobliste_provide_relatives_person_details($pd_args);
jobliste_check('integration off -> no details', [], $pd_args['details']);
$mybb->settings['jobliste_relatives_integration'] = 1;

foreach ([null, 'garbage', 42, [], ['details' => []], ['person_ids' => '5', 'details' => []]] as $i => $broken) {
    $before = $broken;
    jobliste_provide_relatives_person_details($broken);
    jobliste_check('broken args #' . $i . ' left untouched', $before, $broken);
}
$pd_args = ['person_ids' => [$pd_npc], 'linked_uids' => 'garbage', 'details' => 'garbage'];
jobliste_provide_relatives_person_details($pd_args);
jobliste_check('broken linked_uids/details tolerated', 'SELFTEST PD – SELFTEST <b>Heiler</b>', $pd_args['details'][$pd_npc]['job'] ?? null);

// --- Listener Personenformular: form_fields / validate / saved -----------------------------

echo "Relatives person form:\n";
$lang->load('jobliste');
$rf_saved_user = $mybb->user;
$rf_saved_input = $mybb->input;
$rf_saved_team = $mybb->settings['jobliste_team_usergroups'] ?? '';
$rf_saved_enabled = $mybb->settings['jobliste_enabled'] ?? 1;
$mybb->settings['jobliste_enabled'] = 1;
$mybb->settings['jobliste_relatives_integration'] = 1;
$mybb->settings['jobliste_team_usergroups'] = '4';
if (!isset($theme) || !is_array($theme)) {
    $theme = ['templateset' => 1];
}

$rf_uid = 999999970;        // angemeldeter Fixture-Account (spielt $rf_own)
$rf_other = 999999971;      // fremder gespielter Charakter
$rf_person = 999999972;     // Person ohne Account, nicht im Besitz von $rf_uid
$rf_own = 999999974;        // Person, verknuepft mit $rf_uid
$rf_foreign = 999999975;    // Person, verknuepft mit $rf_other
$mybb->user = ['uid' => $rf_uid, 'username' => 'SELFTEST RF', 'usergroup' => 2, 'additionalgroups' => '', 'displaygroup' => 0];
$rf_as_team = static function (bool $team) use ($mybb): void {
    $mybb->user['usergroup'] = $team ? 4 : 2;
};

Jobliste_Categories::clear_cache();
$rf_cat = Jobliste_Categories::create(['name' => 'SELFTEST RF', 'parent_id' => 0, 'allow_custom_text' => 1]);
$rf_cat_plain = Jobliste_Categories::create(['name' => 'SELFTEST RF ohne Freitext', 'parent_id' => 0]);
$rf_lv = Jobliste_Categories::create(['name' => 'SELFTEST RF Ebenen', 'parent_id' => 0, 'type' => 'levels']);
Jobliste_Levels::create($rf_lv, ['label' => 'Land', 'source' => 'list', 'options' => "GB\nFrankreich"]);
Jobliste_Levels::clear_cache();
Jobliste_Categories::clear_cache();

$rf_ctx = static function (int $person_id, int $linked_uid, array $extra = []) use ($rf_uid): array {
    return ['person_id' => $person_id, 'linked_uid' => $linked_uid, 'uid' => $rf_uid] + $extra;
};
$rf_post = static function (array $input) use ($mybb): void {
    $mybb->input = $input;
};
$rf_validate = static function (array $args): array {
    $args += ['errors' => []];
    jobliste_relatives_person_validate($args);
    return $args['errors'];
};
$rf_save = static function (array $args): void {
    $args += ['is_new' => false];
    jobliste_relatives_person_saved($args);
};
$rf_jobs = static function (string $type, int $id): array {
    return Jobliste_Database::get_jobs_by_entity($type, $id);
};

// form_fields
$rf_post([]);
$rf_tpl_ok = $templates->get('jobliste_relatives_fields') !== '';
if ($rf_tpl_ok) {
    $args = $rf_ctx($rf_own, $rf_uid, ['html' => '<p>vorher</p>']);
    jobliste_relatives_person_form_fields($args);
    jobliste_check('form: appended to existing html', 0, strpos($args['html'], '<p>vorher</p>'));
    jobliste_check('form: own prefixed inputs', true, strpos($args['html'], 'name="jobliste_category_id"') !== false
        && strpos($args['html'], 'name="jobliste_custom_label"') !== false
        && strpos($args['html'], 'name="jobliste_submitted" value="1"') !== false);
    jobliste_check('form: no unprefixed names', false, (bool)preg_match('/name="(category_id|custom_label|level\[)/', $args['html']));
    jobliste_check('form: no own <form>', false, stripos($args['html'], '<form') !== false);
    jobliste_check('form: level fields prefixed', true, strpos($args['html'], 'name="jobliste_level[' . $rf_lv . '][0]"') !== false);

    $foreign_job = Jobliste_Database::insert_job(['entity_type' => 'user', 'entity_id' => $rf_other, 'category_id' => $rf_cat, 'custom_label' => 'SELFTEST <i>Auror</i>']);
    $created_jobs[] = $foreign_job;
    $args = $rf_ctx($rf_foreign, $rf_other, ['html' => '']);
    jobliste_relatives_person_form_fields($args);
    jobliste_check('form: foreign played character read-only, escaped', true,
        strpos($args['html'], 'SELFTEST &lt;i&gt;Auror&lt;/i&gt;') !== false && strpos($args['html'], 'jobliste_submitted') === false);
} else {
    echo "  skip form output -- templates missing, re-activate the plugin\n";
}
$mybb->settings['jobliste_relatives_integration'] = 0;
$args = $rf_ctx($rf_own, $rf_uid, ['html' => '']);
jobliste_relatives_person_form_fields($args);
jobliste_check('form: integration off -> nothing', '', $args['html']);
$mybb->settings['jobliste_relatives_integration'] = 1;
foreach ([null, 'garbage', ['person_id' => 'abc', 'uid' => $rf_uid, 'html' => ''], ['person_id' => 1, 'linked_uid' => 0, 'uid' => 12345, 'html' => '']] as $i => $broken) {
    $before = $broken;
    jobliste_relatives_person_form_fields($broken);
    jobliste_relatives_person_validate($broken);
    jobliste_relatives_person_saved($broken);
    jobliste_check('form hooks: broken args #' . $i . ' untouched', $before, $broken);
}

// validate
$rf_post(['jobliste_category_id' => 999999]);
jobliste_check('validate: quick form without jobliste_submitted ignored', [], $rf_validate($rf_ctx($rf_own, $rf_uid)));
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_own, 'jobliste_category_id' => 0]);
jobliste_check('validate: empty choice is fine', [], $rf_validate($rf_ctx($rf_own, $rf_uid)));
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_own, 'jobliste_category_id' => 999999]);
jobliste_check('validate: unknown category', [$lang->jobliste_error_category_required], $rf_validate($rf_ctx($rf_own, $rf_uid)));
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_own, 'jobliste_category_id' => $rf_cat_plain, 'jobliste_custom_label' => 'x']);
jobliste_check('validate: free text not allowed', [$lang->jobliste_error_custom_not_allowed], $rf_validate($rf_ctx($rf_own, $rf_uid)));
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_own, 'jobliste_category_id' => $rf_lv, 'level' => [$rf_lv => ['GB']]]);
jobliste_check('validate: unprefixed level input ignored', [$lang->jobliste_error_levels_required], $rf_validate($rf_ctx($rf_own, $rf_uid)));
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_foreign, 'jobliste_category_id' => $rf_cat]);
jobliste_check('validate: foreign played character ignored, no error', [], $rf_validate($rf_ctx($rf_foreign, $rf_other)));
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => 0, 'jobliste_category_id' => $rf_cat]);
jobliste_check('validate: new person ok', [], $rf_validate($rf_ctx(0, 0)));
// New form, then linked to a foreign played character with "No job" preselected.
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => 0, 'jobliste_category_id' => 0]);
jobliste_check('validate: new form linked to foreign character, no job -> ok', [], $rf_validate($rf_ctx($rf_foreign, $rf_other)));
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => 0, 'jobliste_category_id' => $rf_cat]);
jobliste_check('validate: new form linked to foreign character, job chosen -> ignored', [], $rf_validate($rf_ctx($rf_foreign, $rf_other)));
$args = $rf_ctx($rf_own, $rf_uid, ['errors' => []]);
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_own, 'jobliste_category_id' => 999999]);
$plugins->run_hooks('relatives_person_validate', $args);
jobliste_check('validate: hook registered and fired', [$lang->jobliste_error_category_required], $args['errors']);

// saved
$rf_post(['jobliste_category_id' => $rf_cat, 'jobliste_custom_label' => 'SELFTEST Schnell']);
$rf_save($rf_ctx($rf_own, $rf_uid));
jobliste_check('saved: quick form without jobliste_submitted ignored', [], $rf_jobs('user', $rf_uid));

$rf_foreign_before = $rf_jobs('user', $rf_other);
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => 0, 'jobliste_job_id' => 0, 'jobliste_category_id' => 0]);
$rf_save($rf_ctx($rf_foreign, $rf_other, ['is_new' => true]));
jobliste_check('saved: new form linked to foreign character, no job -> untouched', $rf_foreign_before, $rf_jobs('user', $rf_other));
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => 0, 'jobliste_job_id' => 0, 'jobliste_category_id' => $rf_cat, 'jobliste_custom_label' => 'SELFTEST Fremd']);
$rf_save($rf_ctx($rf_foreign, $rf_other, ['is_new' => true]));
jobliste_check('saved: new form linked to foreign character, job chosen -> untouched', $rf_foreign_before, $rf_jobs('user', $rf_other));

$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_own, 'jobliste_job_id' => 0, 'jobliste_category_id' => $rf_cat, 'jobliste_custom_label' => 'SELFTEST Lehrer']);
$rf_save($rf_ctx($rf_own, $rf_uid));
$rf_list = $rf_jobs('user', $rf_uid);
jobliste_check('saved: job created for own account', [1, $rf_cat, 'SELFTEST Lehrer', $rf_uid],
    [count($rf_list), $rf_list[0]['category_id'] ?? null, $rf_list[0]['custom_label'] ?? null, $rf_list[0]['created_by_uid'] ?? null]);
$rf_first = (int)($rf_list[0]['id'] ?? 0);

$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_own, 'jobliste_job_id' => $rf_first, 'jobliste_category_id' => $rf_cat, 'jobliste_custom_label' => 'SELFTEST Direktor']);
$rf_save($rf_ctx($rf_own, $rf_uid));
$rf_list = $rf_jobs('user', $rf_uid);
jobliste_check('saved: first job updated in place', [1, $rf_first, 'SELFTEST Direktor'], [count($rf_list), $rf_list[0]['id'] ?? 0, $rf_list[0]['custom_label'] ?? null]);

$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => 0, 'jobliste_job_id' => 0, 'jobliste_category_id' => $rf_cat, 'jobliste_custom_label' => 'SELFTEST Doppelt']);
$rf_save($rf_ctx($rf_own, $rf_uid));
jobliste_check('saved: joining an existing person never overwrites', 'SELFTEST Direktor', $rf_jobs('user', $rf_uid)[0]['custom_label'] ?? null);
jobliste_check('saved: ... and adds no second job', 1, count($rf_jobs('user', $rf_uid)));

$rf_second = Jobliste_Database::insert_job(['entity_type' => 'user', 'entity_id' => $rf_uid, 'category_id' => $rf_cat, 'custom_label' => 'SELFTEST Zweitjob']);
$created_jobs[] = $rf_second;
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_own, 'jobliste_job_id' => $rf_first, 'jobliste_category_id' => 0]);
$rf_save($rf_ctx($rf_own, $rf_uid));
jobliste_check('saved: empty choice removes only the edited job', [$rf_second], array_column($rf_jobs('user', $rf_uid), 'id'));

$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_foreign, 'jobliste_job_id' => 0, 'jobliste_category_id' => $rf_cat_plain]);
$rf_save($rf_ctx($rf_foreign, $rf_other));
jobliste_check('saved: foreign played character untouched (non-team)', 'SELFTEST <i>Auror</i>', $rf_jobs('user', $rf_other)[0]['custom_label'] ?? null);

$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_person, 'jobliste_job_id' => 0, 'jobliste_category_id' => $rf_lv, 'jobliste_level' => [$rf_lv => ['Frankreich']]]);
$rf_save($rf_ctx($rf_person, 0));
jobliste_check('saved: foreign relative untouched (non-team)', [], $rf_jobs('relative', $rf_person));
$rf_as_team(true);
$rf_save($rf_ctx($rf_person, 0));
$rf_list = $rf_jobs('relative', $rf_person);
jobliste_check('saved: team sets relative job with level values', [$rf_lv, ['Frankreich']], [$rf_list[0]['category_id'] ?? null, $rf_list[0]['level_values'] ?? null]);

// Fremde job_id (gehoert zu einer anderen Entitaet) darf nicht veraendert werden.
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_person, 'jobliste_job_id' => $rf_second, 'jobliste_category_id' => 0]);
$rf_save($rf_ctx($rf_person, 0));
jobliste_check('saved: job_id of another entity is ignored', true, Jobliste_Database::get_job($rf_second) !== null);
jobliste_check('saved: ... and the own job stays (it was not the edited one)', 1, count($rf_jobs('relative', $rf_person)));
$rf_as_team(false);

$args = $rf_ctx($rf_own, $rf_uid, ['is_new' => false]);
$rf_post(['jobliste_submitted' => 1, 'jobliste_person_id' => $rf_own, 'jobliste_job_id' => $rf_second, 'jobliste_category_id' => 0]);
$plugins->run_hooks('relatives_person_saved', $args);
jobliste_check('saved: hook registered and fired', [], $rf_jobs('user', $rf_uid));

foreach ([['user', $rf_uid], ['user', $rf_other], ['relative', $rf_person], ['relative', $rf_own], ['relative', $rf_foreign]] as [$rf_type, $rf_id]) {
    Jobliste_Database::delete_jobs_by_entity($rf_type, $rf_id);
}
Jobliste_Levels::delete_by_category($rf_lv);
$mybb->user = $rf_saved_user;
$mybb->input = $rf_saved_input;
$mybb->settings['jobliste_team_usergroups'] = $rf_saved_team;
$mybb->settings['jobliste_enabled'] = $rf_saved_enabled;
Jobliste_EntityResolver::clear_cache();

// --- Aufraeumen --------------------------------------------------------------------------

foreach ($created_jobs as $id) {
    Jobliste_Database::delete_job((int)$id);
}
foreach ($created_npcs as $id) {
    Jobliste_Database::delete_npc((int)$id);
}
$query = $db->simple_select('jobliste_categories', 'id', "name LIKE 'SELFTEST %'");
while ($row = $db->fetch_array($query)) {
    Jobliste_Levels::delete_by_category((int)$row['id']);
}
$db->delete_query('jobliste_categories', "name LIKE 'SELFTEST %'");
$db->delete_query('jobliste_jobs', "custom_label LIKE 'SELFTEST%'");
Jobliste_Categories::clear_cache();
jobliste_check('cleanup: no SELFTEST categories left', 0, (int)$db->fetch_field($db->simple_select('jobliste_categories', 'COUNT(*) AS c', "name LIKE 'SELFTEST %'"), 'c'));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
