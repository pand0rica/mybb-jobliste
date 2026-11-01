<?php
/**
 * jobliste - ModCP: Kategorie-Antraege pruefen
 *
 * modcp.php?action=jobliste_requests            Liste offener + zuletzt bearbeiteter Antraege
 * modcp.php?action=jobliste_requests&do=review  Antrag pruefen: annehmen (Name/Ort anpassbar)
 *                                               oder mit Begruendung ablehnen
 * POST sub_action=approve|reject                Entscheidung speichern (+ optional PN)
 *
 * Wird nur aus jobliste_handle_modcp() geladen; Zugriff ist dort bereits geprueft
 * (canmodcp + Team-Gruppe, jobliste_can_handle_requests()).
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

function jobliste_modcp_requests(): void
{
    global $mybb, $lang;

    $lang->load('jobliste');
    add_breadcrumb($lang->jobliste_modcp_title, 'modcp.php?action=jobliste_requests');

    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));
        jobliste_modcp_requests_decide();
        return;
    }

    if ($mybb->get_input('do') === 'review') {
        jobliste_modcp_requests_review($mybb->get_input('id', MyBB::INPUT_INT));
        return;
    }
    jobliste_modcp_requests_list();
}

/** Annehmen/Ablehnen speichern. */
function jobliste_modcp_requests_decide(): void
{
    global $mybb, $lang;

    $id = $mybb->get_input('id', MyBB::INPUT_INT);
    $request = Jobliste_Requests::get($id);
    if ($request === null) {
        error($lang->jobliste_error_not_found);
    }
    $response = $mybb->get_input('response');
    $handled_by = (int)$mybb->user['uid'];
    $sub_action = $mybb->get_input('sub_action');

    if ($sub_action === 'approve') {
        $category_id = 0;
        $error = Jobliste_Requests::approve(
            $id,
            $mybb->get_input('name'),
            $mybb->get_input('parent_id', MyBB::INPUT_INT),
            $mybb->get_input('allow_custom_text', MyBB::INPUT_INT) === 1,
            $response,
            $handled_by,
            $category_id
        );
        if ($error !== '') {
            error(jobliste_request_error($error));
        }
        jobliste_modcp_notify(Jobliste_Requests::get($id));
        redirect('modcp.php?action=jobliste_requests', $lang->jobliste_modcp_approved);
    }

    if ($sub_action === 'reject') {
        $error = Jobliste_Requests::reject($id, $response, $handled_by);
        if ($error !== '') {
            error(jobliste_request_error($error));
        }
        jobliste_modcp_notify(Jobliste_Requests::get($id));
        redirect('modcp.php?action=jobliste_requests', $lang->jobliste_modcp_rejected);
    }

    error($lang->jobliste_error_invalid_action);
}

/** PN an die antragstellende Person (Setting jobliste_requests_pm). */
function jobliste_modcp_notify(?array $request): void
{
    global $mybb, $lang;

    if ($request === null || empty($mybb->settings['jobliste_requests_pm'])) {
        return;
    }
    $approved = $request['status'] === Jobliste_Requests::STATUS_APPROVED;
    // PN-Inhalt ist MyCode, nicht HTML -- Rohtext, Escaping macht der Parser.
    $path = $approved && $request['category_id'] > 0
        ? Jobliste_Categories::get_path_label($request['category_id'])
        : jobliste_request_path($request);
    $response = $request['response'] !== ''
        ? $lang->sprintf($lang->jobliste_request_pm_response, $request['response'])
        : '';
    $subject = $lang->sprintf($approved ? $lang->jobliste_request_pm_subject_approved : $lang->jobliste_request_pm_subject_rejected, $request['name']);
    $message = $lang->sprintf(
        $approved ? $lang->jobliste_request_pm_message_approved : $lang->jobliste_request_pm_message_rejected,
        $path,
        $response,
        $mybb->settings['bburl'] . '/jobliste.php'
    );
    jobliste_send_pm($request['uid'], $subject, $message);
}

/** Profil-Link (Gruppenfarbe) oder Fallback-Text, escaped. */
function jobliste_modcp_user_html(int $uid): string
{
    global $lang;

    $user = $uid > 0 ? Jobliste_EntityResolver::get_user($uid) : null;
    if ($user === null) {
        return htmlspecialchars_uni($lang->jobliste_fallback_user);
    }
    return build_profile_link(format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']), $uid);
}

function jobliste_modcp_requests_list(): void
{
    global $mybb, $lang, $templates, $theme, $headerinclude, $header, $footer, $modcp_nav;

    $pending = Jobliste_Requests::get_pending();
    $handled = Jobliste_Requests::get_handled(20);
    $uids = [];
    foreach (array_merge($pending, $handled) as $request) {
        $uids[] = $request['uid'];
        $uids[] = $request['handled_by_uid'];
    }
    Jobliste_EntityResolver::load_users($uids);

    $jobliste_pending_rows = '';
    $trow = 'trow1';
    foreach ($pending as $request) {
        $request_id = $request['id'];
        $request_path = htmlspecialchars_uni(jobliste_request_path($request));
        $request_note = $request['note'] !== '' ? nl2br(htmlspecialchars_uni($request['note'])) : '&ndash;';
        $request_user = jobliste_modcp_user_html($request['uid']);
        $request_date = my_date('relative', $request['created_at']);
        eval('$jobliste_pending_rows .= "' . $templates->get('jobliste_modcp_request_row') . '";');
        $trow = $trow === 'trow1' ? 'trow2' : 'trow1';
    }
    if ($jobliste_pending_rows === '') {
        $jobliste_pending_rows = '<tr><td class="trow1" colspan="5">' . $lang->jobliste_modcp_no_pending . '</td></tr>';
    }

    $jobliste_handled_rows = '';
    $trow = 'trow1';
    foreach ($handled as $request) {
        $request_path = htmlspecialchars_uni(
            $request['status'] === Jobliste_Requests::STATUS_APPROVED && Jobliste_Categories::get($request['category_id']) !== null
                ? Jobliste_Categories::get_path_label($request['category_id'])
                : jobliste_request_path($request)
        );
        $request_status = htmlspecialchars_uni(jobliste_request_status_label($request['status']));
        $request_status_class = 'jobliste_status_' . htmlspecialchars_uni($request['status']);
        $request_user = jobliste_modcp_user_html($request['uid']);
        $request_handler = jobliste_modcp_user_html($request['handled_by_uid']);
        $request_date = my_date('relative', $request['handled_at']);
        $request_response = $request['response'] !== '' ? nl2br(htmlspecialchars_uni($request['response'])) : '&ndash;';
        eval('$jobliste_handled_rows .= "' . $templates->get('jobliste_modcp_handled_row') . '";');
        $trow = $trow === 'trow1' ? 'trow2' : 'trow1';
    }
    if ($jobliste_handled_rows === '') {
        $jobliste_handled_rows = '<tr><td class="trow1" colspan="5">' . $lang->jobliste_modcp_no_handled . '</td></tr>';
    }

    $page = '';
    eval('$page = "' . $templates->get('jobliste_modcp_requests') . '";');
    output_page($page);
}

function jobliste_modcp_requests_review(int $id): void
{
    global $mybb, $lang, $templates, $theme, $headerinclude, $header, $footer, $modcp_nav;

    $request = Jobliste_Requests::get($id);
    if ($request === null) {
        error($lang->jobliste_error_not_found);
    }
    if ($request['status'] !== Jobliste_Requests::STATUS_PENDING) {
        error(jobliste_request_error(Jobliste_Requests::ERR_NOT_PENDING));
    }
    add_breadcrumb($lang->jobliste_modcp_review_title);

    Jobliste_EntityResolver::load_users([$request['uid']]);
    $request_id = $request['id'];
    $request_path = htmlspecialchars_uni(jobliste_request_path($request));
    $request_note = $request['note'] !== '' ? nl2br(htmlspecialchars_uni($request['note'])) : '&ndash;';
    $request_user = jobliste_modcp_user_html($request['uid']);
    $request_date = my_date('relative', $request['created_at']);
    $review_name = htmlspecialchars_uni($request['name']);
    // Elternkategorie inzwischen geloescht -> Hauptkategorie vorschlagen.
    $parent_id = Jobliste_Requests::is_valid_parent($request['parent_id'], false) ? $request['parent_id'] : 0;
    $review_parent_options = jobliste_parent_options($parent_id, false);
    $review_pm_note = !empty($mybb->settings['jobliste_requests_pm']) ? $lang->jobliste_modcp_pm_note : '';

    $page = '';
    eval('$page = "' . $templates->get('jobliste_modcp_review') . '";');
    output_page($page);
}
