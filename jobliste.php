<?php
/**
 * Jobliste - Frontend-Root
 *
 * Actions:
 *   index (Standard)  eine Seite ohne Tabs, von oben nach unten: Formular "Beruf
 *                     hinzufuegen", Kategorie beantragen + eigene Antraege (Setting
 *                     jobliste_requests_enabled), eigene Berufe + Berufe der Verwandten
 *   add (POST)        Beruf anlegen
 *   edit              Formular (GET) / speichern (POST)
 *   delete (POST)     Beruf loeschen
 *   overview          Gesamtliste: einklappbarer Kategoriebaum + Suchfilter. Eigenstaendige
 *                     Listenseite (keine UserCP-Sidebar), optional im Rahmen der
 *                     forumseigenen Listen (Settings jobliste_overview_*_template)
 *   npc-manage        NPC-Verwaltung (nur Team)
 *   npc-save (POST)   NPC anlegen/aendern (nur Team)
 *   npc-delete (POST) NPC samt Berufen loeschen (nur Team)
 *   request           Altlink: leitet auf den Antrags-Abschnitt der Startseite um
 *                     (bearbeitet werden Antraege im ModCP)
 *   request-save (POST)     Antrag stellen
 *   request-withdraw (POST) eigenen offenen Antrag zurueckziehen
 *
 * Alle Aktionen erfordern Login -- ausser der Gesamtliste, sofern jobliste_overview_guests
 * an ist. Schreibende Aktionen nur per POST + verify_post_check().
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'jobliste.php');

$templatelist = 'jobliste_page,jobliste_index,jobliste_entity_block,jobliste_job_row,jobliste_job_empty,'
    . 'jobliste_job_actions,jobliste_form,jobliste_overview,jobliste_overview_category,jobliste_overview_job,'
    . 'jobliste_overview_page,jobliste_overview_tab,jobliste_overview_panel,jobliste_relation_info,jobliste_relation_info_row,'
    . 'jobliste_npc_manage,jobliste_npc_row,jobliste_npc_form,jobliste_delete_form,jobliste_form_levels,'
    . 'jobliste_request_page,jobliste_request_row,jobliste_request_form';

require_once './global.php';
require_once MYBB_ROOT . 'inc/functions_user.php';

$lang->load('jobliste');

if (!function_exists('jobliste_is_installed') || !jobliste_is_installed()) {
    error_no_permission();
}
if (empty($mybb->settings['jobliste_enabled'])) {
    error($lang->jobliste_disabled);
}
// Ausnahme von der Login-Pflicht: die Gesamtliste, sofern fuer Gaeste freigegeben. Die
// Gruppenberechtigung "Forum ansehen" gilt trotzdem.
$jobliste_is_guest_overview = $mybb->get_input('action') === 'overview' && !empty($mybb->settings['jobliste_overview_guests']);
if ((int)$mybb->user['uid'] <= 0 && (!$jobliste_is_guest_overview || empty($mybb->usergroup['canview']))) {
    error_no_permission();
}

$jobliste_is_team = jobliste_is_team();
$jobliste_js_version = (int)(@filemtime(MYBB_ROOT . 'inc/plugins/jobliste/assets/jobliste.js') ?: TIME_NOW);

// Die Gesamtliste gehoert nicht zum UserCP-Bereich -- sie setzt ihre eigene Breadcrumb.
if ($mybb->get_input('action') !== 'overview') {
    add_breadcrumb($lang->jobliste, 'jobliste.php');
}

// --- Helfer (nur fuer diese Seite) -------------------------------------------------

/**
 * POST + gueltiger my_post_key. Ein GET-Link mit Key wuerde verify_post_check() zwar
 * passieren, den Key aber in Logs/Referrer leaken (debugging.md #3).
 */
function jobliste_verify_post(): void
{
    global $mybb;

    if ($mybb->request_method !== 'post') {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));
}

/** Erlaubte Ruecksprungziele nach einer schreibenden Aktion. */
function jobliste_return_url(string $return): string
{
    switch ($return) {
        case 'overview':
            return 'jobliste.php?action=overview';
        case 'npc-manage':
            return 'jobliste.php?action=npc-manage';
    }
    return 'jobliste.php';
}

function jobliste_clean_return(string $return): string
{
    return in_array($return, ['index', 'overview', 'npc-manage'], true) ? $return : 'index';
}

/**
 * HTML (escaped) fuer die Berufsbezeichnung: Kategoriepfad + optionaler Freitext.
 */
function jobliste_job_html(array $job): string
{
    global $lang;

    $parts = [];
    $path = $job['category_id'] !== null ? Jobliste_Categories::get_path_label((int)$job['category_id']) : '';
    // Ebenenwerte (Kategorietyp "levels") gehoeren zum Pfad: "Muggel › GB › Anderes".
    $segments = array_merge($path !== '' ? [$path] : [], $job['level_values']);
    if ($segments) {
        $parts[] = '<span class="jobliste_path">' . htmlspecialchars_uni(implode(Jobliste_Categories::PATH_SEPARATOR, $segments)) . '</span>';
    }
    if ($job['custom_label'] !== null && $job['custom_label'] !== '') {
        $parts[] = '<span class="jobliste_custom">' . htmlspecialchars_uni($job['custom_label']) . '</span>';
    }
    if (!$parts) {
        return '<span class="jobliste_path">' . $lang->jobliste_no_category . '</span>';
    }
    return implode(' <span class="jobliste_sep">&ndash;</span> ', $parts);
}

/** Rohtext-Beschriftung eines Entitaetstyps (fuer Kennzeichnung in Listen). */
function jobliste_type_label(string $type): string
{
    global $lang;

    switch ($type) {
        case 'relative':
            return $lang->jobliste_type_relative;
        case 'npc':
            return $lang->jobliste_type_npc;
    }
    return $lang->jobliste_type_user;
}

/** HTML (escaped) fuer einen Account: Profil-Link in der Farbe seiner Anzeigegruppe. */
function jobliste_user_link_html(int $uid, string $fallback_label): string
{
    $user = Jobliste_EntityResolver::get_user($uid);
    if ($user === null) {
        return htmlspecialchars_uni($fallback_label);
    }
    $name = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
    return build_profile_link($name, $uid);
}

/** HTML (escaped) fuer den Namen einer Entitaet, Accounts verlinkt aufs Profil. */
function jobliste_entity_html(array $job, string $label): string
{
    if ($job['entity_type'] === 'user' && $job['entity_id'] > 0) {
        return jobliste_user_link_html((int)$job['entity_id'], $label);
    }
    if ($job['entity_type'] === 'relative') {
        // Mit einem Charakter-Account verknuepfte Verwandte erscheinen als dieser Account.
        $linked_uid = Jobliste_EntityResolver::get_linked_uid((int)$job['entity_id']);
        if ($linked_uid > 0) {
            return jobliste_user_link_html($linked_uid, $label);
        }
    }
    return htmlspecialchars_uni($label);
}

/**
 * Kennzeichnung hinter dem Namen in der Gesamtliste: NPCs mit Badge, Verwandte mit
 * Info-Icon, dessen Popup zeigt, wer sie in welchem Grad eingetragen hat. Accounts, die
 * zugleich als Verwandte:r eingetragen sind, bekommen dasselbe Icon, sonst nichts.
 */
function jobliste_entity_marker_html(array $job): string
{
    global $lang, $templates;

    if ($job['entity_type'] === 'npc') {
        return ' <span class="jobliste_badge smalltext">' . htmlspecialchars_uni(jobliste_type_label('npc')) . '</span>';
    }
    $relations = Jobliste_EntityResolver::get_relations_for_job($job);
    $is_plain_relative = $job['entity_type'] === 'relative'
        && Jobliste_EntityResolver::get_linked_uid((int)$job['entity_id']) === 0;
    // Accounts ohne Verwandten-Eintrag: kein Icon (reine Verwandte zeigen auch leer den Hinweis).
    if (!$relations && !$is_plain_relative) {
        return '';
    }

    $relinfo_rows = '';
    foreach ($relations as $relation) {
        $user = Jobliste_EntityResolver::get_user($relation['uid']);
        if ($user === null) {
            continue;
        }
        $relinfo_user = jobliste_user_link_html($relation['uid'], $user['username']);
        $relinfo_grade = $relation['relation_label'] !== ''
            ? ' <span class="jobliste_sep">&ndash;</span> <span class="jobliste_relinfo_grade">' . htmlspecialchars_uni($relation['relation_label']) . '</span>'
            : '';
        eval('$relinfo_rows .= "' . $templates->get('jobliste_relation_info_row') . '";');
    }
    if ($relinfo_rows === '') {
        $relinfo_rows = '<span class="jobliste_relinfo_row smalltext">' . $lang->jobliste_relinfo_none . '</span>';
    }
    $relinfo_id = (int)$job['id'];
    $output = '';
    eval('$output = "' . $templates->get('jobliste_relation_info') . '";');
    return ' ' . $output;
}

/** Rohtext der eintragenden Accounts eines Verwandten (fuer den Suchfilter). */
function jobliste_relation_search_text(array $job): string
{
    $names = [];
    foreach (Jobliste_EntityResolver::get_relations_for_job($job) as $relation) {
        $user = Jobliste_EntityResolver::get_user($relation['uid']);
        if ($user !== null) {
            $names[] = $user['username'];
        }
    }
    return implode(' ', $names);
}

/**
 * Gesamtliste: Verwandte, die bestaetigt mit einem Charakter-Account verknuepft sind,
 * erscheinen als dieser Account. Hat der Account selbst mindestens einen Beruf, gilt nur
 * dieser (die Spielerin des Charakters ist massgeblich) und die Verwandten-Berufe der
 * Person werden ausgeblendet; das Info-Icon bleibt an der Account-Zeile. Sonst erscheint
 * der Verwandten-Beruf als Account-Zeile (Sortier-Label = Accountname).
 *
 * @param array[]               $jobs   nach resolve_labels()
 * @param array<string, string> $labels "type:id" => Label (wird angepasst)
 * @return array[]
 */
function jobliste_merge_linked_relatives(array $jobs, array &$labels): array
{
    $accounts_with_jobs = [];
    foreach ($jobs as $job) {
        if ($job['entity_type'] === 'user') {
            $accounts_with_jobs[(int)$job['entity_id']] = true;
        }
    }

    $result = [];
    foreach ($jobs as $job) {
        if ($job['entity_type'] === 'relative') {
            $linked_uid = Jobliste_EntityResolver::get_linked_uid((int)$job['entity_id']);
            if ($linked_uid > 0) {
                if (isset($accounts_with_jobs[$linked_uid])) {
                    continue; // Account hat eigene Berufe -> diese sind massgeblich
                }
                $labels[Jobliste_EntityResolver::entity_key('relative', (int)$job['entity_id'])]
                    = Jobliste_EntityResolver::get_user($linked_uid)['username'];
            }
        }
        $result[] = $job;
    }
    return $result;
}

/** <select> der Entitaeten, fuer die der User Berufe eintragen darf. */
function jobliste_entity_select(array $entities, string $selected_key): string
{
    global $lang;

    $groups = ['user' => '', 'relative' => '', 'npc' => ''];
    foreach ($entities as $entity) {
        $key = Jobliste_EntityResolver::entity_key($entity['entity_type'], $entity['entity_id']);
        $text = htmlspecialchars_uni($entity['label']);
        if ($entity['relation_label'] !== '') {
            $text .= ' (' . htmlspecialchars_uni($entity['relation_label']) . ')';
        }
        $groups[$entity['entity_type']] .= '<option value="' . htmlspecialchars_uni($key) . '"'
            . ($key === $selected_key ? ' selected="selected"' : '') . '>' . $text . '</option>';
    }

    $html = '<select name="entity" id="jobliste_entity" required="required">';
    $html .= '<optgroup label="' . htmlspecialchars_uni($lang->jobliste_group_own) . '">' . $groups['user'] . '</optgroup>';
    if ($groups['relative'] !== '') {
        $html .= '<optgroup label="' . htmlspecialchars_uni($lang->jobliste_group_relatives) . '">' . $groups['relative'] . '</optgroup>';
    }
    if ($groups['npc'] !== '') {
        $html .= '<optgroup label="' . htmlspecialchars_uni($lang->jobliste_group_npcs) . '">' . $groups['npc'] . '</optgroup>';
    }
    return $html . '</select>';
}

/** Aktions-Links (Bearbeiten/Loeschen) fuer eine Beruf-Zeile. */
function jobliste_job_actions(array $job, string $return, bool $with_delete = true): string
{
    global $mybb, $lang, $templates;

    $job_id = (int)$job['id'];
    $jobliste_return = htmlspecialchars_uni($return);
    $jobliste_delete_form = '';
    if ($with_delete) {
        $form_action = 'delete';
        $form_id = $job_id;
        $form_confirm = $lang->jobliste_confirm_delete;
        $form_button = $lang->jobliste_delete;
        eval('$jobliste_delete_form = "' . $templates->get('jobliste_delete_form') . '";');
    }
    $job_actions = '';
    eval('$job_actions = "' . $templates->get('jobliste_job_actions') . '";');
    return $job_actions;
}

/** Gibt eine UserCP-Seite (mit Sidebar) im gemeinsamen Rahmen aus. */
function jobliste_output(string $title, string $content): void
{
    global $mybb, $lang, $templates, $theme, $headerinclude, $header, $footer, $usercpnav, $jobliste_js_version;

    usercp_menu();
    $jobliste_sidebar = $usercpnav;
    $jobliste_page_title = htmlspecialchars_uni($title);
    $jobliste_content = $content;

    $page = '';
    eval('$page = "' . $templates->get('jobliste_page') . '";');
    output_page($page);
    exit;
}

/**
 * Gibt es ein Template dieses Namens (global, Master oder im aktuellen Template-Set)?
 * Fuer die optionalen Rahmen-/Navigations-Templates der Gesamtliste -- ein Tippfehler im
 * Setting soll auf die eigene Seite zurueckfallen statt eine leere Seite zu liefern.
 */
function jobliste_template_exists(string $title): bool
{
    global $db, $theme;

    if ($title === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $title)) {
        return false;
    }
    $sids = [-2, -1];
    if (isset($theme['templateset'])) {
        $sids[] = (int)$theme['templateset'];
    }
    $row = $db->fetch_array($db->simple_select(
        'templates',
        'tid',
        "title = '" . $db->escape_string($title) . "' AND sid IN (" . implode(',', $sids) . ")",
        ['limit' => 1]
    ));
    return (bool)$row;
}

/**
 * Formular "Beruf hinzufuegen/bearbeiten".
 *
 * @param array|null $job Beruf beim Bearbeiten, null = neu
 */
function jobliste_render_form(?array $job, array $entities, string $return, string $preselect_entity = ''): string
{
    global $mybb, $lang, $templates, $theme;

    $jobliste_return = htmlspecialchars_uni($return);
    if ($job === null) {
        $form_action = 'add';
        $form_job_id = 0;
        $form_title = $lang->jobliste_add_title;
        $form_submit = $lang->jobliste_add_submit;
        $form_entity_field = jobliste_entity_select($entities, $preselect_entity);
        $form_category_options = jobliste_category_options(0);
        $form_level_fields = jobliste_level_fields(0, []);
        $form_custom_label = '';
    } else {
        $form_action = 'edit';
        $form_job_id = (int)$job['id'];
        $form_title = $lang->jobliste_edit_title;
        $form_submit = $lang->jobliste_edit_submit;
        $label = Jobliste_EntityResolver::resolve_label($job);
        $form_entity_field = '<strong>' . htmlspecialchars_uni($label) . '</strong> <span class="smalltext">(' . htmlspecialchars_uni(jobliste_type_label($job['entity_type'])) . ')</span>';
        $form_category_options = jobliste_category_options((int)$job['category_id']);
        $form_level_fields = jobliste_level_fields((int)$job['category_id'], $job['level_values']);
        $form_custom_label = htmlspecialchars_uni((string)$job['custom_label']);
    }
    if ($form_category_options === '') {
        return '<div class="jobliste_notice">' . $lang->jobliste_no_categories . '</div>';
    }
    $form_levels_row = '';
    if ($form_level_fields !== '') {
        eval('$form_levels_row = "' . $templates->get('jobliste_form_levels') . '";');
    }

    $jobliste_form = '';
    eval('$jobliste_form = "' . $templates->get('jobliste_form') . '";');
    return $jobliste_form;
}

/**
 * Abschnitt "Kategorie beantragen" der Startseite: Antragsformular, darunter die eigenen
 * Antraege (nur wenn vorhanden). Leer, wenn Antraege abgeschaltet sind.
 */
function jobliste_render_request_section(): string
{
    global $mybb, $lang, $templates, $theme;

    if (!Jobliste_Requests::enabled()) {
        return '';
    }
    $uid = (int)$mybb->user['uid'];

    if (Jobliste_Requests::count_pending($uid) >= Jobliste_Requests::MAX_PENDING_PER_USER) {
        $jobliste_request_form = '<div class="jobliste_notice">'
            . $lang->sprintf($lang->jobliste_request_error_limit_reached, Jobliste_Requests::MAX_PENDING_PER_USER) . '</div>';
    } else {
        $request_parent_options = jobliste_parent_options(0, true);
        $jobliste_request_form = '';
        eval('$jobliste_request_form = "' . $templates->get('jobliste_request_form') . '";');
    }

    $jobliste_request_rows = '';
    $trow = 'trow1';
    foreach (Jobliste_Requests::get_by_user($uid) as $request) {
        $is_pending = $request['status'] === Jobliste_Requests::STATUS_PENDING;
        // Angenommen: aktueller Pfad der angelegten Kategorie (das Team kann Name/Ort
        // angepasst haben), sonst der beantragte.
        $request_path = htmlspecialchars_uni(
            !$is_pending && $request['category_id'] > 0 && Jobliste_Categories::get($request['category_id']) !== null
                ? Jobliste_Categories::get_path_label($request['category_id'])
                : jobliste_request_path($request)
        );
        $request_status = htmlspecialchars_uni(jobliste_request_status_label($request['status']));
        $request_status_class = 'jobliste_status_' . htmlspecialchars_uni($request['status']);
        $request_response = $request['response'] !== '' ? nl2br(htmlspecialchars_uni($request['response'])) : '&ndash;';
        $request_date = my_date('relative', $is_pending ? $request['created_at'] : $request['handled_at']);
        $request_withdraw = '';
        if ($is_pending) {
            $jobliste_return = 'index';
            $form_action = 'request-withdraw';
            $form_id = $request['id'];
            $form_confirm = $lang->jobliste_request_confirm_withdraw;
            $form_button = $lang->jobliste_request_withdraw;
            eval('$request_withdraw = "' . $templates->get('jobliste_delete_form') . '";');
            $request_withdraw = '<br />' . $request_withdraw;
        }
        eval('$jobliste_request_rows .= "' . $templates->get('jobliste_request_row') . '";');
        $trow = $trow === 'trow1' ? 'trow2' : 'trow1';
    }
    // Ohne eigene Antraege keine leere Tabelle auf der ohnehin langen Seite.
    $jobliste_request_list = '';
    if ($jobliste_request_rows !== '') {
        eval('$jobliste_request_list = "' . $templates->get('jobliste_request_page') . '";');
    }

    return '<div id="jobliste_request">' . $jobliste_request_form . $jobliste_request_list . '</div><br />';
}

// --- Dispatch -----------------------------------------------------------------------

$action = $mybb->get_input('action');
if ($action === '') {
    $action = 'index';
}
$return = jobliste_clean_return($mybb->get_input('return'));

// --- add (POST) ---------------------------------------------------------------------

if ($action === 'add') {
    jobliste_verify_post();

    $entity_parts = explode(':', $mybb->get_input('entity'), 2);
    $entity_type = (string)($entity_parts[0] ?? '');
    $entity_id = isset($entity_parts[1]) ? (int)$entity_parts[1] : 0;

    if (!Jobliste_Database::is_valid_entity_type($entity_type)
        || !Jobliste_EntityResolver::can_manage_entity($mybb->user, $entity_type, $entity_id)) {
        error_no_permission();
    }
    if (Jobliste_EntityResolver::limit_reached($entity_type, $entity_id)) {
        error($lang->sprintf($lang->jobliste_error_limit, (int)$mybb->settings['jobliste_max_jobs_per_entity']));
    }

    [$error, $category_id, $custom_label, $level_values] = jobliste_validate_job_input();
    if ($error !== '') {
        error($error);
    }

    $id = Jobliste_Database::insert_job([
        'entity_type'    => $entity_type,
        'entity_id'      => $entity_id,
        'category_id'    => $category_id,
        'custom_label'   => $custom_label,
        'level_values'   => $level_values,
        'created_by_uid' => (int)$mybb->user['uid'],
    ]);
    if ($id <= 0) {
        error($lang->jobliste_error_save);
    }
    redirect(jobliste_return_url($return), $lang->jobliste_saved);
}

// --- edit (GET Formular / POST speichern) ------------------------------------------

if ($action === 'edit') {
    $job = Jobliste_Database::get_job($mybb->get_input('id', MyBB::INPUT_INT));
    if ($job === null) {
        error($lang->jobliste_error_not_found);
    }
    if (!Jobliste_EntityResolver::can_edit_job($mybb->user, $job)) {
        error_no_permission();
    }

    if ($mybb->request_method === 'post') {
        jobliste_verify_post();
        [$error, $category_id, $custom_label, $level_values] = jobliste_validate_job_input($job['category_id'], $job['level_values']);
        if ($error !== '') {
            error($error);
        }
        Jobliste_Database::update_job((int)$job['id'], $category_id, $custom_label, $level_values);
        redirect(jobliste_return_url($return), $lang->jobliste_saved);
    }

    add_breadcrumb($lang->jobliste_edit_title);
    $content = jobliste_render_form($job, [], $return);

    // Loeschen direkt vom Bearbeiten-Formular aus (auch fuer die Uebersicht, die nur
    // einen Bearbeiten-Link zeigt).
    $jobliste_return = htmlspecialchars_uni($return);
    $form_action = 'delete';
    $form_id = (int)$job['id'];
    $form_confirm = $lang->jobliste_confirm_delete;
    $form_button = $lang->jobliste_delete_job;
    $jobliste_delete_form = '';
    eval('$jobliste_delete_form = "' . $templates->get('jobliste_delete_form') . '";');
    $content .= '<div class="jobliste_delete_block">' . $jobliste_delete_form . '</div>';

    jobliste_output($lang->jobliste_edit_title, $content);
}

// --- delete (POST) -----------------------------------------------------------------

if ($action === 'delete') {
    jobliste_verify_post();
    $job = Jobliste_Database::get_job($mybb->get_input('id', MyBB::INPUT_INT));
    if ($job === null) {
        error($lang->jobliste_error_not_found);
    }
    if (!Jobliste_EntityResolver::can_edit_job($mybb->user, $job)) {
        error_no_permission();
    }
    Jobliste_Database::delete_job((int)$job['id']);
    redirect(jobliste_return_url($return), $lang->jobliste_deleted);
}

// --- NPC-Aktionen (nur Team) --------------------------------------------------------

if (in_array($action, ['npc-manage', 'npc-save', 'npc-delete'], true) && !$jobliste_is_team) {
    error_no_permission();
}

if ($action === 'npc-save') {
    jobliste_verify_post();
    $npc_id = $mybb->get_input('npc_id', MyBB::INPUT_INT);
    $name = $mybb->get_input('name');
    $notes = $mybb->get_input('notes');
    if (Jobliste_Database::clean_label($name) === null) {
        error($lang->jobliste_error_npc_name);
    }
    if ($npc_id > 0) {
        if (Jobliste_Database::get_npc($npc_id) === null) {
            error($lang->jobliste_error_not_found);
        }
        Jobliste_Database::update_npc($npc_id, $name, $notes);
    } else {
        $npc_id = Jobliste_Database::insert_npc($name, $notes, (int)$mybb->user['uid']);
        if ($npc_id <= 0) {
            error($lang->jobliste_error_save);
        }
    }
    redirect('jobliste.php?action=npc-manage', $lang->jobliste_npc_saved);
}

if ($action === 'npc-delete') {
    jobliste_verify_post();
    $npc_id = $mybb->get_input('id', MyBB::INPUT_INT);
    if (Jobliste_Database::get_npc($npc_id) === null) {
        error($lang->jobliste_error_not_found);
    }
    Jobliste_Database::delete_npc($npc_id);
    redirect('jobliste.php?action=npc-manage', $lang->jobliste_npc_deleted);
}

if ($action === 'npc-manage') {
    add_breadcrumb($lang->jobliste_nav_npcs);

    $npcs = Jobliste_Database::get_npcs();
    $npc_entities = [];
    foreach ($npcs as $npc) {
        $npc_entities[] = ['entity_type' => 'npc', 'entity_id' => $npc['id']];
    }
    $jobs_by_npc = [];
    foreach (Jobliste_Database::get_jobs_by_entities($npc_entities) as $job) {
        $jobs_by_npc[$job['entity_id']][] = $job;
    }

    $jobliste_npc_rows = '';
    $trow = 'trow1';
    foreach ($npcs as $npc) {
        $npc_id = $npc['id'];
        $npc_name = htmlspecialchars_uni($npc['name']);
        $npc_notes = nl2br(htmlspecialchars_uni($npc['notes']));
        $npc_jobs = '';
        foreach ($jobs_by_npc[$npc_id] ?? [] as $job) {
            $npc_jobs .= '<li>' . jobliste_job_html($job) . ' ' . jobliste_job_actions($job, 'npc-manage') . '</li>';
        }
        $npc_jobs = $npc_jobs !== '' ? '<ul class="jobliste_jobs">' . $npc_jobs . '</ul>' : '<span class="smalltext">' . $lang->jobliste_no_jobs . '</span>';
        $jobliste_return = 'npc-manage';
        $form_action = 'npc-delete';
        $form_id = $npc_id;
        $form_confirm = $lang->jobliste_confirm_delete_npc;
        $form_button = $lang->jobliste_delete;
        $jobliste_delete_form = '';
        eval('$jobliste_delete_form = "' . $templates->get('jobliste_delete_form') . '";');
        eval('$jobliste_npc_rows .= "' . $templates->get('jobliste_npc_row') . '";');
        $trow = $trow === 'trow1' ? 'trow2' : 'trow1';
    }
    if ($jobliste_npc_rows === '') {
        $jobliste_npc_rows = '<tr><td class="trow1" colspan="4"><span class="smalltext">' . $lang->jobliste_no_npcs . '</span></td></tr>';
    }

    // NPC-Formular (neu oder bearbeiten)
    $edit_npc = Jobliste_Database::get_npc($mybb->get_input('edit', MyBB::INPUT_INT));
    $npc_form_id = $edit_npc !== null ? $edit_npc['id'] : 0;
    $npc_form_title = $edit_npc !== null ? $lang->jobliste_npc_edit_title : $lang->jobliste_npc_add_title;
    $npc_form_name = $edit_npc !== null ? htmlspecialchars_uni($edit_npc['name']) : '';
    $npc_form_notes = $edit_npc !== null ? htmlspecialchars_uni($edit_npc['notes']) : '';
    $jobliste_npc_form = '';
    eval('$jobliste_npc_form = "' . $templates->get('jobliste_npc_form') . '";');

    $content = '';
    eval('$content = "' . $templates->get('jobliste_npc_manage') . '";');
    jobliste_output($lang->jobliste_nav_npcs, $content);
}

// --- Kategorie-Antraege ---------------------------------------------------------------

if (in_array($action, ['request', 'request-save', 'request-withdraw'], true) && !Jobliste_Requests::enabled()) {
    error($lang->jobliste_request_disabled);
}

if ($action === 'request-save') {
    jobliste_verify_post();
    $error = Jobliste_Requests::create(
        (int)$mybb->user['uid'],
        $mybb->get_input('name'),
        $mybb->get_input('parent_id', MyBB::INPUT_INT),
        $mybb->get_input('note')
    );
    if ($error !== '') {
        error(jobliste_request_error($error));
    }
    redirect('jobliste.php#jobliste_request', $lang->jobliste_request_saved);
}

if ($action === 'request-withdraw') {
    jobliste_verify_post();
    if (!Jobliste_Requests::withdraw($mybb->get_input('id', MyBB::INPUT_INT), (int)$mybb->user['uid'])) {
        error(jobliste_request_error(Jobliste_Requests::ERR_NOT_PENDING));
    }
    redirect('jobliste.php#jobliste_request', $lang->jobliste_request_withdrawn);
}

// Frueher ein eigener Tab -- der Abschnitt steht jetzt auf der Startseite.
if ($action === 'request') {
    header('Location: ' . $mybb->settings['bburl'] . '/jobliste.php#jobliste_request');
    exit;
}

// --- overview ------------------------------------------------------------------------

if ($action === 'overview') {
    add_breadcrumb($lang->jobliste_overview_title, 'jobliste.php?action=overview');

    $all_jobs = Jobliste_Database::get_all_jobs();
    // Integration aus: Verwandten-Eintraege bleiben erhalten, werden aber nicht gelistet.
    if (!jobliste_relatives_integration_enabled()) {
        $all_jobs = array_values(array_filter($all_jobs, static function (array $job): bool {
            return $job['entity_type'] !== 'relative';
        }));
    }
    $labels = Jobliste_EntityResolver::resolve_labels($all_jobs);
    // Verwandte, die Relatives nicht (mehr) kennt -- Person geloescht oder Plugin inaktiv --,
    // gehoeren nicht in die Liste. Aufraeumen: ACP -> Job-Moderation.
    $all_jobs = array_values(array_filter($all_jobs, static function (array $job): bool {
        return $job['entity_type'] !== 'relative' || Jobliste_EntityResolver::is_resolved($job);
    }));
    $all_jobs = jobliste_merge_linked_relatives($all_jobs, $labels);

    $jobs_by_category = [];
    foreach ($all_jobs as $job) {
        $cat_id = $job['category_id'] !== null && Jobliste_Categories::get($job['category_id']) !== null ? $job['category_id'] : 0;
        $jobs_by_category[$cat_id][] = $job;
    }

    $all_categories = Jobliste_Categories::get_all();
    $children_map = Jobliste_Categories::get_children_map();

    // Jobs je Teilbaum zaehlen (fuer die Zahl im <summary> und zum Ausblenden leerer,
    // inaktiver Zweige).
    $subtree_counts = [];
    $count_subtree = static function (int $id, array $seen = []) use (&$count_subtree, &$subtree_counts, $children_map, $jobs_by_category): int {
        if (isset($subtree_counts[$id])) {
            return $subtree_counts[$id];
        }
        if (isset($seen[$id])) {
            return 0;
        }
        $seen[$id] = true;
        $count = isset($jobs_by_category[$id]) ? count($jobs_by_category[$id]) : 0;
        foreach ($children_map[$id] ?? [] as $child) {
            $count += $count_subtree($child, $seen);
        }
        return $subtree_counts[$id] = $count;
    };

    $render_jobs = static function (array $jobs) use ($labels, $mybb, $lang, $templates): string {
        usort($jobs, static function (array $a, array $b) use ($labels): int {
            $la = $labels[Jobliste_EntityResolver::entity_key($a['entity_type'], $a['entity_id'])];
            $lb = $labels[Jobliste_EntityResolver::entity_key($b['entity_type'], $b['entity_id'])];
            return strcasecmp($la, $lb);
        });
        $html = '';
        foreach ($jobs as $job) {
            $label = $labels[Jobliste_EntityResolver::entity_key($job['entity_type'], $job['entity_id'])];
            $job_type = htmlspecialchars_uni(
                ($job['entity_type'] === 'relative' && Jobliste_EntityResolver::get_linked_uid((int)$job['entity_id']) > 0)
                    ? 'user'
                    : $job['entity_type']
            );
            $job_marker_html = jobliste_entity_marker_html($job);
            $job_entity_html = jobliste_entity_html($job, $label);
            $job_custom_html = ($job['custom_label'] !== null && $job['custom_label'] !== '')
                ? ' <span class="jobliste_sep">&ndash;</span> <span class="jobliste_custom">' . htmlspecialchars_uni($job['custom_label']) . '</span>'
                : '';
            $job_search_text = htmlspecialchars_uni($label . ' ' . (string)$job['custom_label'] . ' ' . jobliste_relation_search_text($job));
            // Icon link instead of a button per entry (conventions.md §9 exempts icon links).
            $job_actions = Jobliste_EntityResolver::can_edit_job($mybb->user, $job)
                ? ' <a href="jobliste.php?action=edit&amp;id=' . (int)$job['id'] . '&amp;return=overview" class="jobliste_edit_link"'
                    . ' title="' . htmlspecialchars_uni($lang->jobliste_edit) . '" aria-label="' . htmlspecialchars_uni($lang->jobliste_edit) . '">'
                    . '<svg width="13" height="13" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M11.5 1.5l3 3L5 14H2v-3z M9.5 3.5l3 3" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></a>'
                : '';
            eval('$html .= "' . $templates->get('jobliste_overview_job') . '";');
        }
        return $html !== '' ? '<ul class="jobliste_jobs">' . $html . '</ul>' : '';
    };

    // Kategorien vom Typ "levels": dynamische Unterknoten je vorkommendem Ebenenwert
    // (Muggel › GB › Anderes › Personen), Eintraege ohne Wert auf einer Ebene bleiben
    // direkt am uebergeordneten Knoten.
    $render_level_groups = static function (array $jobs, int $index, int $depth) use (&$render_level_groups, $render_jobs, $templates, $lang): string {
        $direct = [];
        $groups = [];
        foreach ($jobs as $job) {
            if (isset($job['level_values'][$index])) {
                $groups[(string)$job['level_values'][$index]][] = $job;
            } else {
                $direct[] = $job;
            }
        }
        uksort($groups, static function ($a, $b): int {
            return strcasecmp((string)$a, (string)$b);
        });
        $html = $render_jobs($direct);
        foreach ($groups as $value => $group_jobs) {
            $cat_children = $render_level_groups($group_jobs, $index + 1, $depth + 1);
            $cat_jobs = '';
            $cat_name = htmlspecialchars_uni((string)$value);
            $cat_count = count($group_jobs);
            $cat_depth = min($depth, 6);
            $cat_open = ' open="open"';
            $cat_empty_class = ' jobliste_cat_level';
            eval('$html .= "' . $templates->get('jobliste_overview_category') . '";');
        }
        return $html;
    };

    // Inhalt einer Kategorie (eigene Berufe + Unterkategorien), Unterknoten auf $depth.
    $render_body = static function (int $id, int $depth, array $seen) use (&$render_category, $render_jobs, $render_level_groups, $all_categories, $children_map, $jobs_by_category): array {
        $children = '';
        foreach ($children_map[$id] ?? [] as $child) {
            $children .= $render_category($child, $depth, $seen);
        }
        $jobs = $all_categories[$id]['type'] === 'levels'
            ? $render_level_groups($jobs_by_category[$id] ?? [], 0, $depth)
            : $render_jobs($jobs_by_category[$id] ?? []);
        return [$jobs, $children];
    };

    $render_category = static function (int $id, int $depth, array $seen = []) use (&$render_category, $render_body, $count_subtree, $all_categories, $templates, $lang): string {
        if (isset($seen[$id]) || !isset($all_categories[$id])) {
            return '';
        }
        $seen[$id] = true;
        $category = $all_categories[$id];
        $total = $count_subtree($id);
        if ($category['is_active'] !== 1 && $total === 0) {
            return '';
        }

        [$cat_jobs, $cat_children] = $render_body($id, $depth + 1, $seen);
        $cat_name = htmlspecialchars_uni($category['name']);
        $cat_count = $total;
        $cat_depth = min($depth, 6);
        $cat_open = ' open="open"';
        $cat_empty_class = $total === 0 ? ' jobliste_cat_empty' : '';
        $output = '';
        eval('$output = "' . $templates->get('jobliste_overview_category') . '";');
        return $output;
    };

    // Je Wurzelkategorie (z. B. GB / nicht GB / Muggel) ein Tab; die Wurzel selbst ist
    // der Tab, ihre Unterkategorien stehen im Panel auf oberster Ebene. Ohne JS werden
    // alle Panels untereinander mit Ueberschrift angezeigt.
    $jobliste_tab_buttons = '';
    $jobliste_tree = '';
    $add_panel = static function (string $key, string $name, int $count, string $body) use (&$jobliste_tab_buttons, &$jobliste_tree, $templates, $lang): void {
        $panel_key = htmlspecialchars_uni($key);
        $panel_name = $name;
        $panel_count = $count;
        $panel_body = $body !== '' ? $body : '<p class="smalltext">' . $lang->jobliste_overview_panel_empty . '</p>';
        eval('$jobliste_tab_buttons .= "' . $templates->get('jobliste_overview_tab') . '";');
        eval('$jobliste_tree .= "' . $templates->get('jobliste_overview_panel') . '";');
    };
    foreach ($children_map[0] ?? [] as $root_id) {
        if (!isset($all_categories[$root_id])) {
            continue;
        }
        $total = $count_subtree($root_id);
        if ($all_categories[$root_id]['is_active'] !== 1 && $total === 0) {
            continue;
        }
        [$panel_jobs, $panel_children] = $render_body($root_id, 0, [$root_id => true]);
        $add_panel((string)$root_id, htmlspecialchars_uni($all_categories[$root_id]['name']), $total, $panel_jobs . $panel_children);
    }
    if (!empty($jobs_by_category[0])) {
        $add_panel('none', $lang->jobliste_no_category, count($jobs_by_category[0]), $render_jobs($jobs_by_category[0]));
    }
    if ($jobliste_tree === '') {
        $jobliste_tree = '<p class="smalltext">' . $lang->jobliste_overview_empty . '</p>';
    }
    $jobliste_overview_count = $lang->sprintf($lang->jobliste_overview_count, count($all_jobs));

    $jobliste_overview_content = '';
    eval('$jobliste_overview_content = "' . $templates->get('jobliste_overview') . '";');

    // Optional in den Rahmen der forumseigenen Listen einhaengen (z. B. listen.php mit den
    // Templates "listen" + "listen_nav"), wie die Familienliste des Relatives-Plugins. Der
    // Inhalt steht dem Rahmen unter beiden Variablennamen zur Verfuegung, damit bestehende
    // Listen-Templates unveraendert passen.
    $layout_template = trim((string)($mybb->settings['jobliste_overview_layout_template'] ?? ''));
    $nav_template = trim((string)($mybb->settings['jobliste_overview_nav_template'] ?? ''));
    $page = '';
    if ($layout_template !== '' && jobliste_template_exists($layout_template)) {
        $headline = $lang->jobliste_overview_title;
        $listen_content = $jobliste_overview_content;
        $listen_nav = '';
        if ($nav_template !== '' && jobliste_template_exists($nav_template)) {
            eval('$listen_nav = "' . $templates->get($nav_template) . '";');
        }
        $jobliste_list_nav = $listen_nav;
        eval('$page = "' . $templates->get($layout_template) . '";');
    } else {
        eval('$page = "' . $templates->get('jobliste_overview_page') . '";');
    }
    output_page($page);
    exit;
}

// --- index (Standard) ------------------------------------------------------------------

if ($action !== 'index') {
    error($lang->jobliste_error_invalid_action);
}

$entities = Jobliste_EntityResolver::collect_entities($mybb->user);
// NPCs werden auf der NPC-Seite gelistet; hier nur eigener Account + Verwandte.
$own_entities = array_values(array_filter($entities, static function (array $entity): bool {
    return $entity['entity_type'] !== 'npc';
}));
$jobs = Jobliste_Database::get_jobs_by_entities($own_entities);
$jobs_by_entity = [];
foreach ($jobs as $job) {
    $jobs_by_entity[Jobliste_EntityResolver::entity_key($job['entity_type'], $job['entity_id'])][] = $job;
}

$jobliste_entity_blocks = '';
foreach ($own_entities as $entity) {
    $key = Jobliste_EntityResolver::entity_key($entity['entity_type'], $entity['entity_id']);
    // Verwandte ohne Berufe nicht auflisten (sonst endlos lange Seite) -- sie sind ueber
    // die Auswahl im Formular erreichbar. Der eigene Account erscheint immer.
    if ($entity['entity_type'] !== 'user' && empty($jobs_by_entity[$key])) {
        continue;
    }
    $entity_label = htmlspecialchars_uni($entity['label']);
    $entity_relation = $entity['relation_label'] !== ''
        ? ' <span class="smalltext">(' . htmlspecialchars_uni($entity['relation_label']) . ')</span>'
        : ' <span class="smalltext">(' . htmlspecialchars_uni(jobliste_type_label($entity['entity_type'])) . ')</span>';
    $entity_rows = '';
    $trow = 'trow1';
    foreach ($jobs_by_entity[$key] ?? [] as $job) {
        $job_html = jobliste_job_html($job);
        $job_actions = jobliste_job_actions($job, 'index');
        eval('$entity_rows .= "' . $templates->get('jobliste_job_row') . '";');
        $trow = $trow === 'trow1' ? 'trow2' : 'trow1';
    }
    if ($entity_rows === '') {
        eval('$entity_rows = "' . $templates->get('jobliste_job_empty') . '";');
    }
    eval('$jobliste_entity_blocks .= "' . $templates->get('jobliste_entity_block') . '";');
}

$limit_note = '';
$max_jobs = (int)($mybb->settings['jobliste_max_jobs_per_entity'] ?? 0);
if ($max_jobs > 0) {
    $limit_note = '<p class="smalltext">' . $lang->sprintf($lang->jobliste_limit_note, $max_jobs) . '</p>';
}

$preselect = $mybb->get_input('entity');
$jobliste_form = jobliste_render_form(null, $entities, 'index', $preselect);
$jobliste_request_section = jobliste_render_request_section();
// NPC-Verwaltung (nur Team) -- frueher ein Tab, jetzt ein Link neben der Gesamtliste.
$jobliste_npc_link = $jobliste_is_team
    ? ' &middot; <a href="jobliste.php?action=npc-manage">' . htmlspecialchars_uni($lang->jobliste_nav_npcs) . '</a>'
    : '';

$content = '';
eval('$content = "' . $templates->get('jobliste_index') . '";');
jobliste_output($lang->jobliste_nav_mine, $content);
