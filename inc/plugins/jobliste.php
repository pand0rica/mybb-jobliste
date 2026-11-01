<?php
/**
 * Jobliste
 *
 * Hauptdatei: info()-Block, Lifecycle-Delegation an Jobliste_Installer, Hook-
 * Registrierung, Hook-Handler, ACP-Registrierung und kleine gemeinsame Helfer
 * (Team-Pruefung, Fallback-Label).
 *
 * Siehe PROJECT_CONTEXT.md fuer Architektur/Tabellen/Hooks/"What NOT to change".
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// Core-Klassen werden immer geladen (auch vor der Installation -- jobliste_install()
// braucht Jobliste_Installer bereits beim allerersten Aufruf).
require_once MYBB_ROOT . "inc/plugins/jobliste/core/Installer.php";
require_once MYBB_ROOT . "inc/plugins/jobliste/core/Categories.php";
require_once MYBB_ROOT . "inc/plugins/jobliste/core/Levels.php";
require_once MYBB_ROOT . "inc/plugins/jobliste/core/Database.php";
require_once MYBB_ROOT . "inc/plugins/jobliste/core/EntityResolver.php";
require_once MYBB_ROOT . "inc/plugins/jobliste/core/Migration.php";
require_once MYBB_ROOT . "inc/plugins/jobliste/core/Requests.php";
require_once MYBB_ROOT . "inc/plugins/jobliste/core/FormFields.php";

// Templates der Kategorie-Antraege mit in den Template-Cache der Seite aufnehmen (global.php
// cacht $templatelist erst nach dem Laden der Plugins).
if (defined('THIS_SCRIPT') && !defined('IN_ADMINCP')) {
    global $templatelist;
    if (THIS_SCRIPT === 'index.php') {
        $templatelist = (isset($templatelist) ? $templatelist . ',' : '') . 'jobliste_request_notice';
    } elseif (THIS_SCRIPT === 'modcp.php') {
        $templatelist = (isset($templatelist) ? $templatelist . ',' : '')
            . 'jobliste_modcp_nav,jobliste_modcp_requests,jobliste_modcp_request_row,jobliste_modcp_handled_row,jobliste_modcp_review';
    } elseif (THIS_SCRIPT === 'relatives.php') {
        // Beruf-Felder im Personenformular (Hook relatives_person_form_fields)
        $templatelist = (isset($templatelist) ? $templatelist . ',' : '')
            . 'jobliste_relatives_fields,jobliste_relatives_levels,jobliste_relatives_readonly';
    }
}

// --- info() -------------------------------------------------------------------------

function jobliste_info(): array
{
    return [
        "name"          => "Jobliste",
        "description"   => "Verwaltet einen ACP-pflegbaren Kategoriebaum (Arbeitsorte/Branchen) und laesst Nutzer:innen Berufe fuer sich, ihre Verwandten (optional, Relatives-Plugin) und das Team fuer NPCs eintragen.",
        "website"       => "https://github.com/pand0rica/jobliste",
        "author"        => "pand0rica",
        "authorsite"    => "https://github.com/pand0rica",
        "version"       => "2.0.0",
        "guid"          => "",
        "codename"      => "jobliste",
        "compatibility" => "18*",
    ];
}

// --- Lifecycle (Delegation an Jobliste_Installer) ------------------------------------

function jobliste_install(): void
{
    Jobliste_Installer::install();
}

function jobliste_is_installed(): bool
{
    return Jobliste_Installer::is_installed();
}

function jobliste_uninstall(): void
{
    Jobliste_Installer::uninstall();
}

function jobliste_activate(): void
{
    Jobliste_Installer::activate();
}

function jobliste_deactivate(): void
{
    Jobliste_Installer::deactivate();
}

// --- Hook-Registrierung ---------------------------------------------------------------

$plugins->add_hook("usercp_menu", "jobliste_handle_usercp_menu");
$plugins->add_hook("datahandler_user_delete_start", "jobliste_handle_user_delete");
$plugins->add_hook("index_start", "jobliste_handle_index_notice");
$plugins->add_hook("modcp_nav", "jobliste_handle_modcp_nav");
$plugins->add_hook("modcp_start", "jobliste_handle_modcp");
$plugins->add_hook("relatives_collect_person_details", "jobliste_provide_relatives_person_details");
$plugins->add_hook("relatives_person_form_fields", "jobliste_relatives_person_form_fields");
$plugins->add_hook("relatives_person_validate", "jobliste_relatives_person_validate");
$plugins->add_hook("relatives_person_saved", "jobliste_relatives_person_saved");

$plugins->add_hook("admin_tools_menu", "jobliste_admin_menu");
$plugins->add_hook("admin_tools_action_handler", "jobliste_admin_action");

// --- Hook-Handler: usercp_menu --------------------------------------------------------

/**
 * Die Menuezeile selbst ist statisch in usercp_nav_misc eingebacken (siehe
 * Jobliste_Installer::install_usercp_nav_misc_link(), debugging.md Punkt 20). Dieser
 * Hook laedt nur die Sprachdatei, damit {$lang->jobliste_usercp_menu_item} aufgeloest
 * wird -- $lang ist ein Objekt und damit in jedem eval()-Scope dieselbe Instanz.
 */
function jobliste_handle_usercp_menu(): void
{
    global $lang;

    $lang->load('jobliste');
}

// --- Hook-Handler: datahandler_user_delete_start -------------------------------------

/**
 * Deckt ALLE Loeschwege ab (ACP einzeln/bulk, Merge, Frontend). UserDataHandler::
 * delete_user() legt die zu loeschenden uids vor dem Hook in $delete_uids ab (Array).
 * Loescht die eigenen Berufe (entity_type='user') der geloeschten Accounts und setzt
 * created_by_uid bei allen von ihnen angelegten Eintraegen (z.B. NPC-Berufe als Team)
 * auf 0 -- diese Eintraege bleiben erhalten.
 */
function jobliste_handle_user_delete(&$userhandler): void
{
    if (!is_object($userhandler) || !isset($userhandler->delete_uids)) {
        return;
    }
    $uids = is_array($userhandler->delete_uids)
        ? $userhandler->delete_uids
        : explode(',', (string)$userhandler->delete_uids);

    foreach ($uids as $uid) {
        $uid = (int)$uid;
        if ($uid <= 0) {
            continue;
        }
        Jobliste_Database::delete_jobs_by_entity('user', $uid);
        Jobliste_Database::release_created_by($uid);
        Jobliste_Requests::handle_user_delete($uid);
    }
}

// --- Hook-Listener: relatives_collect_person_details ----------------------------------

/**
 * Provider fuer das Relatives-Plugin: liefert den Beruf je Person (Stammbaum-Panel,
 * "Meine Verwandten", Gesuchs-Karten). Gespielte Charaktere (linked_uids) ueber ihren
 * Account (entity_type 'user'), alle anderen Personen ueber entity_type 'relative'.
 * Mehrere Berufe einer Entitaet werden mit ", " verbunden (nach id). Reiner Text --
 * Relatives escapet beim Ausgeben. Liest nur jobliste_* (eine Query fuer alle Personen).
 * Kaputte Args oder Integration aus = still nichts tun; wirft nie.
 *
 * Die Core-Klassen sind ueber die Hauptdatei bereits geladen (require_once oben), auch im
 * Frontend (relatives.php, member.php).
 *
 * @param array $args ['person_ids' => int[], 'linked_uids' => [person_id => uid], 'details' => []]
 */
function jobliste_provide_relatives_person_details(&$args): void
{
    if (!is_array($args) || !isset($args['person_ids']) || !is_array($args['person_ids'])) {
        return;
    }
    if (!jobliste_relatives_integration_enabled()) {
        return;
    }
    if (!isset($args['details']) || !is_array($args['details'])) {
        $args['details'] = [];
    }
    $linked_uids = isset($args['linked_uids']) && is_array($args['linked_uids']) ? $args['linked_uids'] : [];

    try {
        // Entitaets-Schluessel ("user:12" / "relative:5") -> zugehoerige person_ids
        $persons_by_key = [];
        $entities = [];
        foreach ($args['person_ids'] as $person_id) {
            $person_id = (int)$person_id;
            if ($person_id <= 0) {
                continue;
            }
            $uid = isset($linked_uids[$person_id]) ? (int)$linked_uids[$person_id] : 0;
            $type = $uid > 0 ? 'user' : 'relative';
            $entity_id = $uid > 0 ? $uid : $person_id;
            $key = $type . ':' . $entity_id;
            if (!isset($persons_by_key[$key])) {
                $entities[] = ['entity_type' => $type, 'entity_id' => $entity_id];
            }
            $persons_by_key[$key][$person_id] = $person_id;
        }
        if (!$entities || !jobliste_is_installed()) {
            return;
        }

        // get_jobs_by_entities(): eine Query, IDs per (int), sortiert nach id
        $labels_by_key = [];
        foreach (Jobliste_Database::get_jobs_by_entities($entities) as $job) {
            $text = jobliste_job_text($job);
            if ($text !== '') {
                $labels_by_key[$job['entity_type'] . ':' . $job['entity_id']][] = $text;
            }
        }

        foreach ($labels_by_key as $key => $labels) {
            foreach ($persons_by_key[$key] ?? [] as $person_id) {
                $args['details'][$person_id]['job'] = implode(', ', $labels);
            }
        }
    } catch (Throwable $e) {
        // Ein Fehler hier darf die Relatives-Seite nie brechen.
        return;
    }
}

// --- Hook-Listener: Beruf im Personenformular von Relatives -----------------------------
//
// relatives_person_form_fields -> Felder ausgeben (Input-Namen mit Praefix "jobliste_")
// relatives_person_validate    -> vor jedem Schreibzugriff pruefen
// relatives_person_saved       -> nach dem Speichern anlegen/aendern/loeschen
//
// Zuordnung: gespielter Charakter (linked_uid > 0) -> entity_type 'user' + linked_uid,
// sonst 'relative' + person_id. Mehrere Berufe: das Formular bearbeitet nur den ersten
// (aeltesten), alle weiteren bleiben unangetastet und werden in der Jobliste verwaltet.
// Validierung/Speichern nur, wenn jobliste_submitted=1 mitkam -- das Schnellformular im
// Stammbaum postet ohne diese Felder und darf den Beruf nicht anfassen.
// Kaputte Args = still nichts tun; die Listener werfen nie.

/** Laeuft die Anbindung ans Personenformular? (Master-Schalter + Integration + installiert) */
function jobliste_relatives_form_active(): bool
{
    global $mybb;

    return !empty($mybb->settings['jobliste_enabled'])
        && jobliste_relatives_integration_enabled()
        && jobliste_is_installed();
}

/**
 * Normalisiert den Hook-Kontext von Relatives und leitet die Jobliste-Entitaet ab.
 * entity_id ist 0 bei einer neuen, noch nicht gespeicherten Person. null bei kaputten
 * Args oder wenn uid nicht der angemeldete Account ist (Rechte haengen an $mybb->user).
 *
 * @param mixed $args
 * @return array|null ['person_id', 'linked_uid', 'uid', 'entity_type', 'entity_id']
 */
function jobliste_relatives_context($args): ?array
{
    global $mybb;

    if (!is_array($args)) {
        return null;
    }
    foreach (['person_id', 'linked_uid', 'uid'] as $key) {
        if (isset($args[$key]) && !is_numeric($args[$key])) {
            return null;
        }
    }
    $person_id = (int)($args['person_id'] ?? -1);
    $linked_uid = (int)($args['linked_uid'] ?? 0);
    $uid = (int)($args['uid'] ?? 0);
    if ($person_id < 0 || $linked_uid < 0 || $uid <= 0 || $uid !== (int)($mybb->user['uid'] ?? 0)) {
        return null;
    }
    return [
        'person_id'   => $person_id,
        'linked_uid'  => $linked_uid,
        'uid'         => $uid,
        'entity_type' => $linked_uid > 0 ? 'user' : 'relative',
        'entity_id'   => $linked_uid > 0 ? $linked_uid : $person_id,
    ];
}

/**
 * Darf der angemeldete Account den Beruf dieser Person setzen? Wie in der Jobliste:
 * Team immer; gespielte Charaktere nur der Account selbst; Verwandte nur, wer sie ueber
 * jobliste_collect_entities geliefert bekommt (= eigene Personen bei Relatives).
 *
 * @param bool $person_pending true = Person wird gerade erst angelegt bzw. einer
 *                             bestehenden beigetreten -- Relatives macht sie danach zur
 *                             eigenen; die Besitzpruefung folgt in relatives_person_saved.
 */
function jobliste_relatives_can_set_job(array $ctx, bool $person_pending): bool
{
    global $mybb;

    if (jobliste_is_team()) {
        return true;
    }
    if ($ctx['entity_type'] === 'user') {
        return $ctx['entity_id'] === $ctx['uid'];
    }
    if ($ctx['entity_id'] <= 0 || $person_pending) {
        return true;
    }
    return Jobliste_EntityResolver::can_manage_entity($mybb->user, 'relative', $ctx['entity_id']);
}

/**
 * Liest und prueft die Formularfelder (Praefix "jobliste_").
 *
 * jobliste_person_id = person_id, fuer die das Formular erzeugt wurde (0 = Neuanlage).
 * jobliste_job_id    = bearbeiteter (erster) Beruf, 0 = es gab keinen.
 *
 * @param bool $person_saved false im Validate-Hook (Person evtl. noch nicht gespeichert)
 * @return array ['error' => string, 'skip' => bool (Beruf nicht anfassen), 'job' => ?array
 *               (bearbeiteter Beruf), 'remove' => bool, 'category_id' => int,
 *               'custom_label' => ?string, 'level_values' => ?array, 'new_form' => bool]
 */
function jobliste_relatives_read_input(array $ctx, bool $person_saved): array
{
    global $mybb, $lang;

    $lang->load('jobliste');
    $new_form = $mybb->get_input('jobliste_person_id', MyBB::INPUT_INT) === 0;
    $result = [
        'error'        => '',
        'skip'         => false,
        'job'          => null,
        'remove'       => false,
        'category_id'  => 0,
        'custom_label' => null,
        'level_values' => null,
        'new_form'     => $new_form,
    ];
    $empty_choice = $mybb->get_input('jobliste_category_id', MyBB::INPUT_INT) <= 0
        && Jobliste_Database::clean_label($mybb->get_input('jobliste_custom_label')) === null;

    if (!jobliste_relatives_can_set_job($ctx, $new_form && !$person_saved)) {
        // Typisch: neues Formular, Relatives verknuepft die Person beim Speichern mit einem
        // bestehenden gespielten Charakter eines anderen Accounts. Dessen Beruf pflegt nur
        // der Account selbst -- Eingabe ignorieren, statt das Speichern der Person (und
        // ihrer Verbindungen) zu blockieren. Geschrieben wird nichts.
        $result['skip'] = true;
        return $result;
    }

    // Bearbeiteter Beruf: nur einer, der wirklich zu dieser Entitaet gehoert.
    $job_id = $mybb->get_input('jobliste_job_id', MyBB::INPUT_INT);
    if (!$new_form && $job_id > 0 && $ctx['entity_id'] > 0) {
        foreach (Jobliste_Database::get_jobs_by_entity($ctx['entity_type'], $ctx['entity_id']) as $job) {
            if ($job['id'] === $job_id) {
                $result['job'] = $job;
                break;
            }
        }
    }

    // Leere Auswahl = Beruf entfernen bzw. keinen anlegen, kein Fehler.
    if ($empty_choice) {
        $result['remove'] = true;
        return $result;
    }

    $current = $result['job'];
    [$error, $category_id, $custom_label, $level_values] = jobliste_validate_job_input(
        $current !== null ? $current['category_id'] : null,
        $current !== null ? $current['level_values'] : [],
        'jobliste_'
    );
    $result['error'] = $error;
    $result['category_id'] = $category_id;
    $result['custom_label'] = $custom_label;
    $result['level_values'] = $level_values;
    return $result;
}

/**
 * Hook relatives_person_form_fields: Beruf-Felder fuer das Personenformular. Haengt
 * fertig escaptes HTML an $args['html'] an (kein eigenes <form>). Ohne Kategorien wird
 * nichts ausgegeben. Darf der Account den Beruf nicht setzen (fremder gespielter
 * Charakter), wird er nur angezeigt.
 *
 * @param array $args ['person_id', 'linked_uid', 'uid', 'html']
 */
function jobliste_relatives_person_form_fields(&$args): void
{
    global $mybb, $lang, $templates;

    if (!is_array($args) || !jobliste_relatives_form_active()) {
        return;
    }
    try {
        $ctx = jobliste_relatives_context($args);
        if ($ctx === null) {
            return;
        }
        $lang->load('jobliste');

        $jobs = $ctx['entity_id'] > 0 ? Jobliste_Database::get_jobs_by_entity($ctx['entity_type'], $ctx['entity_id']) : [];
        $job = $jobs[0] ?? null;

        // Nur anzeigen: fremder gespielter Charakter -- oder der erste Beruf hat keine (mehr
        // existierende) Kategorie (Altdaten); in der Auswahl waere dann nichts gewaehlt und
        // das Formular wuerde ihn beim Speichern loeschen.
        if (!jobliste_relatives_can_set_job($ctx, $ctx['person_id'] === 0)
            || ($job !== null && ($job['category_id'] === null || Jobliste_Categories::get((int)$job['category_id']) === null))) {
            $texts = array_values(array_filter(array_map('jobliste_job_text', $jobs), 'strlen'));
            if (!$texts) {
                return;
            }
            $jobliste_rel_job_text = htmlspecialchars_uni(implode(', ', $texts));
            if (jobliste_relatives_can_set_job($ctx, false)) {
                $jobliste_rel_readonly_hint = $lang->jobliste_relatives_readonly_manage;
            } elseif ($ctx['entity_type'] === 'user') {
                $jobliste_rel_readonly_hint = $lang->jobliste_relatives_readonly_hint;
            } else {
                $jobliste_rel_readonly_hint = $lang->jobliste_relatives_readonly_foreign;
            }
            $html = '';
            eval('$html = "' . $templates->get('jobliste_relatives_readonly') . '";');
            $args['html'] = (is_string($args['html'] ?? null) ? $args['html'] : '') . $html;
            return;
        }

        $current_category_id = $job !== null ? (int)$job['category_id'] : 0;
        $jobliste_rel_category_options = jobliste_category_options($current_category_id);
        if ($jobliste_rel_category_options === '') {
            return; // keine Kategorien angelegt
        }
        $jobliste_rel_none_selected = $current_category_id === 0 ? ' selected="selected"' : '';
        $jobliste_rel_person_id = $ctx['person_id'];
        $jobliste_rel_job_id = $job !== null ? $job['id'] : 0;
        $jobliste_rel_custom_label = $job !== null ? htmlspecialchars_uni((string)$job['custom_label']) : '';

        $jobliste_rel_levels_row = '';
        $jobliste_rel_level_fields = jobliste_level_fields($current_category_id, $job !== null ? $job['level_values'] : [], 'jobliste_');
        if ($jobliste_rel_level_fields !== '') {
            eval('$jobliste_rel_levels_row = "' . $templates->get('jobliste_relatives_levels') . '";');
        }

        $jobliste_rel_more = '';
        if (count($jobs) > 1) {
            $jobliste_rel_more = '<div class="smalltext jobliste_relatives_more">'
                . $lang->sprintf($lang->jobliste_relatives_more_jobs, count($jobs) - 1) . '</div>';
        }
        // Kategorie fehlt? Antraege laufen wie gewohnt ueber die Jobliste.
        $jobliste_rel_request_link = Jobliste_Requests::enabled()
            ? ' <a href="' . $mybb->settings['bburl'] . '/jobliste.php#jobliste_request">' . $lang->jobliste_relatives_request_link . '</a>'
            : '';
        $jobliste_js_version = (int)(@filemtime(MYBB_ROOT . 'inc/plugins/jobliste/assets/jobliste.js') ?: TIME_NOW);

        $html = '';
        eval('$html = "' . $templates->get('jobliste_relatives_fields') . '";');
        $args['html'] = (is_string($args['html'] ?? null) ? $args['html'] : '') . $html;
    } catch (Throwable $e) {
        return;
    }
}

/**
 * Hook relatives_person_validate: prueft die Beruf-Felder vor jedem Schreibzugriff.
 * Fehler als Rohtext an $args['errors'] (Relatives escapet). Ohne jobliste_submitted=1
 * (Schnellformular im Stammbaum) passiert nichts.
 *
 * @param array $args ['person_id', 'linked_uid', 'uid', 'errors']
 */
function jobliste_relatives_person_validate(&$args): void
{
    global $mybb;

    if (!is_array($args) || !jobliste_relatives_form_active()
        || $mybb->get_input('jobliste_submitted', MyBB::INPUT_INT) !== 1) {
        return;
    }
    try {
        $ctx = jobliste_relatives_context($args);
        if ($ctx === null) {
            return;
        }
        $input = jobliste_relatives_read_input($ctx, false);
        if ($input['error'] !== '') {
            if (!isset($args['errors']) || !is_array($args['errors'])) {
                $args['errors'] = [];
            }
            $args['errors'][] = $input['error'];
        }
    } catch (Throwable $e) {
        return;
    }
}

/**
 * Hook relatives_person_saved: legt den Beruf an, aendert oder loescht ihn.
 *   - leere Auswahl          -> bearbeiteten Beruf loeschen (weitere bleiben)
 *   - bestehender Beruf      -> aendern
 *   - kein Beruf im Formular -> anlegen, aber nur wenn die Entitaet noch keinen hat (z. B.
 *     bei einer bestehenden Person, der man beitritt: ergaenzen, nie ueberschreiben)
 *
 * @param array $args ['person_id', 'linked_uid', 'uid', 'is_new']
 */
function jobliste_relatives_person_saved(&$args): void
{
    global $mybb;

    if (!is_array($args) || !jobliste_relatives_form_active()
        || $mybb->get_input('jobliste_submitted', MyBB::INPUT_INT) !== 1) {
        return;
    }
    try {
        $ctx = jobliste_relatives_context($args);
        if ($ctx === null || $ctx['entity_id'] <= 0) {
            return;
        }
        // Die Person kann gerade erst angelegt worden sein -> Entitaeten neu sammeln.
        Jobliste_EntityResolver::clear_cache();
        $input = jobliste_relatives_read_input($ctx, true);
        if ($input['error'] !== '' || $input['skip']) {
            return;
        }

        $job = $input['job'];
        if ($input['remove']) {
            if ($job !== null) {
                Jobliste_Database::delete_job($job['id']);
            }
            return;
        }
        if ($job !== null) {
            Jobliste_Database::update_job($job['id'], $input['category_id'], $input['custom_label'], $input['level_values']);
            return;
        }
        if (Jobliste_Database::count_jobs_for_entity($ctx['entity_type'], $ctx['entity_id']) > 0) {
            return;
        }
        Jobliste_Database::insert_job([
            'entity_type'    => $ctx['entity_type'],
            'entity_id'      => $ctx['entity_id'],
            'category_id'    => $input['category_id'],
            'custom_label'   => $input['custom_label'],
            'level_values'   => $input['level_values'],
            'created_by_uid' => $ctx['uid'],
        ]);
    } catch (Throwable $e) {
        return;
    }
}

// --- Kategorie-Antraege: Startseiten-Hinweis + ModCP ------------------------------------

/**
 * Darf der aktuelle User Kategorie-Antraege bearbeiten? Team-Gruppe (Setting) UND
 * ModCP-Zugang -- modcp.php selbst verlangt canmodcp.
 */
function jobliste_can_handle_requests(): bool
{
    global $mybb;

    return !empty($mybb->settings['jobliste_enabled'])
        && Jobliste_Requests::enabled()
        && !empty($mybb->usergroup['canmodcp'])
        && jobliste_is_team();
}

/**
 * Roter Balken auf der Startseite ({$jobliste_request_notice}, im Template index hinter
 * {$header}), solange offene Kategorie-Antraege warten.
 */
function jobliste_handle_index_notice(): void
{
    global $lang, $templates, $jobliste_request_notice;

    $jobliste_request_notice = '';
    if (!jobliste_can_handle_requests()) {
        return;
    }
    $count = Jobliste_Requests::count_pending();
    if ($count <= 0) {
        return;
    }
    $lang->load('jobliste');
    $jobliste_request_notice_text = $count === 1
        ? $lang->jobliste_request_notice_one
        : $lang->sprintf($lang->jobliste_request_notice_many, $count);
    eval('$jobliste_request_notice = "' . $templates->get('jobliste_request_notice') . '";');
}

/**
 * ModCP-Menuepunkt ({$jobliste_modcp_nav} in modcp_nav). Muss im Hook modcp_nav gesetzt
 * werden: modcp.php wertet das Template modcp_nav vor modcp_start aus.
 */
function jobliste_handle_modcp_nav(): void
{
    global $lang, $templates, $jobliste_modcp_nav;

    $jobliste_modcp_nav = '';
    if (!jobliste_can_handle_requests()) {
        return;
    }
    $lang->load('jobliste');
    $count = Jobliste_Requests::count_pending();
    $jobliste_modcp_badge = $count > 0 ? ' (' . $count . ')' : '';
    eval('$jobliste_modcp_nav = "' . $templates->get('jobliste_modcp_nav') . '";');
}

/** ModCP-Seite modcp.php?action=jobliste_requests (Antraege pruefen). */
function jobliste_handle_modcp(): void
{
    global $mybb;

    if ($mybb->get_input('action') !== 'jobliste_requests') {
        return;
    }
    if (!jobliste_can_handle_requests()) {
        error_no_permission();
    }
    require_once MYBB_ROOT . 'inc/plugins/jobliste/modules/modcp/requests.php';
    jobliste_modcp_requests();
    exit;
}

/**
 * <option>-Liste fuer "uebergeordnete Kategorie" eines Antrags: erste Option = neue
 * Hauptkategorie, danach der Baum ohne Kategorien vom Typ "levels" (die haben keine
 * manuellen Unterkategorien).
 *
 * @param bool $active_only Frontend: inaktive Zweige ausblenden; Team (ModCP): alle zeigen
 */
function jobliste_parent_options(int $selected_id, bool $active_only): string
{
    global $lang;

    $lang->load('jobliste');
    $options = '<option value="0"' . ($selected_id === 0 ? ' selected="selected"' : '') . '>'
        . htmlspecialchars_uni($lang->jobliste_request_parent_root) . '</option>';
    foreach (Jobliste_Categories::get_flat_tree($active_only) as $cat) {
        if ($cat['type'] === 'levels') {
            continue;
        }
        $inactive = $cat['is_active'] !== 1 ? ' ' . $lang->jobliste_request_inactive_suffix : '';
        $options .= '<option value="' . $cat['id'] . '"' . ($cat['id'] === $selected_id ? ' selected="selected"' : '') . '>'
            . str_repeat('&nbsp;&nbsp;&nbsp;', $cat['depth'] + 1) . htmlspecialchars_uni($cat['name'] . $inactive) . '</option>';
    }
    return $options;
}

/** Rohtext-Pfad der beantragten Kategorie ("Hogwarts › Kueche"), unescaped. */
function jobliste_request_path(array $request): string
{
    $parent = $request['parent_id'] > 0 ? Jobliste_Categories::get_path_label($request['parent_id']) : '';
    return ($parent !== '' ? $parent . Jobliste_Categories::PATH_SEPARATOR : '') . $request['name'];
}

/** Sprachtext zu einem Fehlercode von Jobliste_Requests. */
function jobliste_request_error(string $code): string
{
    global $lang;

    $lang->load('jobliste');
    $key = 'jobliste_request_error_' . $code;
    return isset($lang->$key)
        ? $lang->sprintf($lang->$key, Jobliste_Requests::MAX_PENDING_PER_USER)
        : $lang->jobliste_error_save;
}

/** Sprachtext zu einem Antragsstatus. */
function jobliste_request_status_label(string $status): string
{
    global $lang;

    $lang->load('jobliste');
    $key = 'jobliste_request_status_' . $status;
    return isset($lang->$key) ? $lang->$key : $status;
}

/**
 * Schickt eine PN wie private.php und feuert private_do_send_end, damit Benachrichtigungs-
 * Plugins (MyAlerts, Account-Switcher ...) reagieren -- send_pm() tut das nicht.
 * Absender = aktueller User. Fehler werden still ignoriert (PN ist nur Zusatzinfo).
 */
function jobliste_send_pm(int $to_uid, string $subject, string $message): void
{
    global $mybb, $plugins, $session, $pm, $pmhandler, $pminfo;

    $recipient = get_user($to_uid);
    if (empty($recipient['username']) || (int)$mybb->user['uid'] <= 0 || $to_uid === (int)$mybb->user['uid']) {
        return;
    }

    require_once MYBB_ROOT . 'inc/datahandlers/pm.php';
    $pm = [
        'subject'     => $subject,
        'message'     => $message,
        'icon'        => -1,
        'fromid'      => (int)$mybb->user['uid'],
        'do'          => '',
        'pmid'        => 0,
        'to'          => [(string)$recipient['username']],
        'bcc'         => [],
        'options'     => ['signature' => 0, 'disablesmilies' => 0, 'savecopy' => 0, 'readreceipt' => 0],
        'saveasdraft' => 0,
    ];
    if (is_object($session)) {
        $pm['ipaddress'] = $session->packedip;
    }
    $pmhandler = new PMDataHandler();
    $pmhandler->admin_override = true;
    $pmhandler->set_data($pm);
    if ($pmhandler->validate_pm()) {
        $pminfo = $pmhandler->insert_pm();
        $plugins->run_hooks('private_do_send_end');
    }
}

// --- ACP-Registrierung ----------------------------------------------------------------

function jobliste_admin_menu(&$sub_menu): void
{
    global $lang;

    $lang->load('jobliste');

    $sub_menu[] = [
        'id'    => 'jobliste',
        'title' => $lang->jobliste,
        'link'  => 'index.php?module=tools-jobliste',
    ];
}

function jobliste_admin_action(&$actions): void
{
    $actions['jobliste'] = ['active' => 'jobliste', 'file' => 'jobliste.php'];
}

// --- Gemeinsame Helfer ----------------------------------------------------------------

/**
 * Gehoert der (aktuelle bzw. uebergebene) User zu einer der in jobliste_team_usergroups
 * konfigurierten Gruppen? Team darf NPC-Eintraege anlegen und fremde Eintraege
 * moderieren. Leere Einstellung = niemand ist Team.
 *
 * @param array|null $user MyBB-Userzeile, null = aktueller User
 */
function jobliste_is_team(?array $user = null): bool
{
    global $mybb;

    $groups = isset($mybb->settings['jobliste_team_usergroups']) ? (string)$mybb->settings['jobliste_team_usergroups'] : '';
    if (trim($groups) === '') {
        return false;
    }
    if ($user === null) {
        $user = $mybb->user;
    }
    if (!isset($user['uid']) || (int)$user['uid'] <= 0) {
        return false;
    }
    if (!isset($user['additionalgroups'])) {
        $user['additionalgroups'] = '';
    }
    return !empty(is_member($groups, $user));
}

/**
 * Ist die Verwandten-Integration eingeschaltet? Aus = die Provider-Hooks werden gar
 * nicht erst gefeuert, auch wenn das Relatives-Plugin aktiv ist.
 */
function jobliste_relatives_integration_enabled(): bool
{
    global $mybb;

    return !empty($mybb->settings['jobliste_relatives_integration']);
}

/**
 * Antwortet das Relatives-Plugin wirklich auf jobliste_resolve_entity_label? Integration an,
 * Plugin laut Plugin-Cache aktiv, dort eingeschaltet (relatives_enabled) und ein Listener
 * registriert. Nur dann bedeutet "Person nicht gefunden" auch "Person existiert nicht mehr".
 */
function jobliste_relatives_provider_active(): bool
{
    global $mybb, $cache, $plugins;

    if (!jobliste_relatives_integration_enabled() || empty($mybb->settings['relatives_enabled'])) {
        return false;
    }
    if (!is_object($plugins) || empty($plugins->hooks['jobliste_resolve_entity_label'])) {
        return false;
    }
    $active = is_object($cache) ? $cache->read('plugins') : null;
    return is_array($active) && !empty($active['active']['relatives']);
}

/**
 * Berufsbezeichnung als Rohtext (unescaped), Klartext-Gegenstueck zu jobliste_job_html():
 * Kategoriepfad + Ebenenwerte ("Muggel › GB › Anderes"), optional " – Freitext".
 * '' wenn weder Kategorie noch Freitext vorhanden sind.
 */
function jobliste_job_text(array $job): string
{
    $parts = [];
    $category_id = isset($job['category_id']) ? (int)$job['category_id'] : 0;
    $path = $category_id > 0 ? Jobliste_Categories::get_path_label($category_id) : '';
    $levels = isset($job['level_values']) && is_array($job['level_values']) ? $job['level_values'] : [];
    $segments = array_merge($path !== '' ? [$path] : [], $levels);
    if ($segments) {
        $parts[] = implode(Jobliste_Categories::PATH_SEPARATOR, $segments);
    }
    if (isset($job['custom_label']) && $job['custom_label'] !== '') {
        $parts[] = (string)$job['custom_label'];
    }
    return implode(' – ', $parts);
}

/**
 * Rohtext-Label (unescaped) fuer eine Job-Zeile, deren Entitaet nicht aufgeloest werden
 * konnte (Account geloescht, NPC geloescht, Relatives-Plugin inaktiv/Integration aus).
 */
function jobliste_fallback_label(array $job): string
{
    global $lang;

    $lang->load('jobliste');
    $type = isset($job['entity_type']) ? (string)$job['entity_type'] : '';
    switch ($type) {
        case 'user':
            return $lang->jobliste_fallback_user;
        case 'npc':
            return $lang->jobliste_fallback_npc;
        case 'relative':
            return $lang->jobliste_fallback_relative;
    }
    return $lang->jobliste_fallback_unknown;
}
