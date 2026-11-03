<?php
/**
 * jobliste ACP - Tab "Job-Moderation": alle Berufe durchsuchen/filtern/loeschen.
 * Unabhaengig von jobliste_team_usergroups -- ACP-Zugriff ist ohnehin Admin-only.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$do = $mybb->get_input('do');
$base_url = 'index.php?module=tools-jobliste&action=moderation';

// Filter (GET), werden in Links/Redirects weitergereicht.
$filter_type = $mybb->get_input('entity_type');
if (!Jobliste_Database::is_valid_entity_type($filter_type)) {
    $filter_type = '';
}
$filter_category = $mybb->get_input('category_id', MyBB::INPUT_INT);
$filter_search = trim($mybb->get_input('search'));
$current_page = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
$per_page = 50;

$filter_query = '&entity_type=' . urlencode($filter_type)
    . '&category_id=' . $filter_category
    . '&search=' . urlencode($filter_search);

// --- do=delete (POST) -----------------------------------------------------------------

if ($do === 'delete' && $mybb->request_method === 'post') {
    jobliste_acp_verify_key();
    $job = Jobliste_Database::get_job($mybb->get_input('id', MyBB::INPUT_INT));
    if ($job === null) {
        flash_message($lang->jobliste_acp_not_found, 'error');
    } else {
        Jobliste_Database::delete_job((int)$job['id']);
        flash_message($lang->jobliste_acp_job_deleted, 'success');
    }
    admin_redirect($base_url . $filter_query . '&page=' . $current_page);
}

// --- do=cleanup_orphans (POST): verwaiste Verwandten-Berufe loeschen ------------------

if ($do === 'cleanup_orphans' && $mybb->request_method === 'post') {
    jobliste_acp_verify_key();
    $orphans = Jobliste_EntityResolver::find_orphaned_relative_jobs();
    foreach ($orphans as $job) {
        Jobliste_Database::delete_job((int)$job['id']);
    }
    flash_message($lang->sprintf($lang->jobliste_acp_orphans_deleted, count($orphans)), 'success');
    admin_redirect($base_url);
}

// --- Liste ------------------------------------------------------------------------------

$page->add_breadcrumb_item($lang->jobliste_tab_moderation, $base_url);
$page->output_header($lang->jobliste_acp_title);
jobliste_acp_nav_tabs('moderation');

// Hinweis auf verwaiste Verwandten-Berufe (nur wenn Relatives aktiv ist und antwortet).
$orphan_count = count(Jobliste_EntityResolver::find_orphaned_relative_jobs());
if ($orphan_count > 0) {
    echo '<div class="alert">' . $lang->sprintf($lang->jobliste_acp_orphans_notice, $orphan_count) . ' '
        . jobliste_acp_post_link(
            $lang->jobliste_acp_orphans_cleanup,
            $base_url . '&do=cleanup_orphans',
            [],
            $lang->jobliste_acp_orphans_confirm
        ) . '</div>';
}

// Filterformular (GET)
$type_options = [
    ''         => $lang->jobliste_acp_filter_all,
    'user'     => $lang->jobliste_type_user,
    'relative' => $lang->jobliste_type_relative,
    'npc'      => $lang->jobliste_type_npc,
];
$category_options = [
    0  => $lang->jobliste_acp_filter_all,
    -1 => $lang->jobliste_acp_no_category,
];
foreach (Jobliste_Categories::get_flat_tree(false) as $cat) {
    $category_options[$cat['id']] = str_repeat('— ', $cat['depth']) . $cat['name'];
}

$form = new Form('index.php', 'get');
echo $form->generate_hidden_field('module', 'tools-jobliste');
echo $form->generate_hidden_field('action', 'moderation');
$form_container = new FormContainer($lang->jobliste_acp_filter_title);
$form_container->output_row($lang->jobliste_acp_filter_type, '', $form->generate_select_box('entity_type', $type_options, [$filter_type], ['id' => 'entity_type']), 'entity_type');
$form_container->output_row($lang->jobliste_acp_filter_category, '', $form->generate_select_box('category_id', $category_options, [$filter_category], ['id' => 'category_id']), 'category_id');
$form_container->output_row($lang->jobliste_acp_filter_search, $lang->jobliste_acp_filter_search_desc, $form->generate_text_box('search', $filter_search, ['id' => 'search']), 'search');
$form_container->end();
$form->output_submit_wrapper([$form->generate_submit_button($lang->jobliste_acp_filter_submit)]);
$form->end();

$result = Jobliste_Database::search_jobs([
    'entity_type' => $filter_type,
    'category_id' => $filter_category,
    'search'      => $filter_search,
], $current_page, $per_page);

$labels = Jobliste_EntityResolver::resolve_labels($result['rows']);

$creator_names = [];
$creator_ids = array_filter(array_unique(array_column($result['rows'], 'created_by_uid')));
if ($creator_ids) {
    $query = $db->simple_select('users', 'uid, username', 'uid IN (' . implode(',', array_map('intval', $creator_ids)) . ')');
    while ($row = $db->fetch_array($query)) {
        $creator_names[(int)$row['uid']] = (string)$row['username'];
    }
}

$table = new Table();
$table->construct_header($lang->jobliste_acp_col_id, ['width' => '5%']);
$table->construct_header($lang->jobliste_acp_col_entity, ['width' => '22%']);
$table->construct_header($lang->jobliste_acp_col_job);
$table->construct_header($lang->jobliste_acp_col_created_by, ['width' => '12%']);
$table->construct_header($lang->jobliste_acp_col_updated, ['width' => '14%']);
$table->construct_header($lang->jobliste_acp_col_actions, ['class' => 'align_center', 'width' => '8%']);

if (empty($result['rows'])) {
    $table->construct_cell($lang->jobliste_acp_no_jobs_found, ['colspan' => 6]);
    $table->construct_row();
} else {
    foreach ($result['rows'] as $job) {
        $key = Jobliste_EntityResolver::entity_key($job['entity_type'], $job['entity_id']);
        $entity = htmlspecialchars_uni($labels[$key]);
        if ($job['entity_type'] === 'user') {
            $entity = build_profile_link($entity, (int)$job['entity_id'], '_blank');
        }
        $entity .= ' <span class="smalltext">(' . htmlspecialchars_uni(jobliste_acp_type_label($job['entity_type'])) . ' #' . (int)$job['entity_id'] . ')</span>';

        $legacy = $job['legacy_job_id'] !== null ? ' <span class="smalltext">(' . $lang->jobliste_acp_legacy . ' #' . (int)$job['legacy_job_id'] . ')</span>' : '';

        $table->construct_cell((string)$job['id']);
        $table->construct_cell($entity);
        $table->construct_cell(jobliste_acp_job_html($job) . $legacy);
        $table->construct_cell(isset($creator_names[$job['created_by_uid']]) ? htmlspecialchars_uni($creator_names[$job['created_by_uid']]) : '&ndash;');
        $table->construct_cell($job['updated_at'] > 0 ? my_date('relative', $job['updated_at']) : '&ndash;', ['class' => 'smalltext']);
        $table->construct_cell(
            jobliste_acp_post_link(
                $lang->jobliste_acp_delete,
                $base_url . '&do=delete' . $filter_query . '&page=' . $current_page,
                ['id' => $job['id']],
                $lang->jobliste_acp_confirm_delete_job
            ),
            ['class' => 'align_center']
        );
        $table->construct_row();
    }
}

$table->output($lang->sprintf($lang->jobliste_acp_jobs_overview, $result['total']));

if ($result['total'] > $per_page) {
    echo draw_admin_pagination($current_page, $per_page, $result['total'], $base_url . htmlspecialchars_uni($filter_query) . '&amp;page={page}');
}

$page->output_footer();
