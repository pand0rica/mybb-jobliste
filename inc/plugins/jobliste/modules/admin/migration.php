<?php
/**
 * jobliste ACP - Tab "Migration" (Legacy-Import aus relatives_items / jobs /
 * relatives.beruf / wanted.beruf). Siehe PROJECT_CONTEXT.md "Migration".
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$base_url = 'index.php?module=tools-jobliste&action=migration';
$tables = Jobliste_Migration::legacy_tables_available();
$last_run = Jobliste_Migration::last_run();

// --- do=run (POST) ----------------------------------------------------------------------

$report = null;
if ($mybb->get_input('do') === 'run' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $report = Jobliste_Migration::run([
        'force'            => $mybb->get_input('force', MyBB::INPUT_INT) === 1,
        'profilefield_fid' => $mybb->get_input('profilefield_fid', MyBB::INPUT_INT),
    ]);
    if (isset($report['error'])) {
        flash_message($lang->{$report['error']}, 'error');
        admin_redirect($base_url);
    }
    $last_run = Jobliste_Migration::last_run();
    // Bericht wird direkt unten ausgegeben (kein Redirect, sonst waere er weg).
}

$page->add_breadcrumb_item($lang->jobliste_tab_migration, $base_url);
$page->output_header($lang->jobliste_acp_title);
jobliste_acp_nav_tabs('migration');

if ($report !== null) {
    $table = new Table();
    $rows = [
        $lang->jobliste_acp_report_categories_created   => $report['categories_created'],
        $lang->jobliste_acp_report_categories_existing  => $report['categories_existing'],
        $lang->jobliste_acp_report_level_categories     => $report['level_categories'] ? htmlspecialchars_uni(implode(', ', $report['level_categories'])) : '&ndash;',
        $lang->jobliste_acp_report_jobs_user            => $report['jobs_user'],
        $lang->jobliste_acp_report_jobs_relative        => $report['jobs_relative'],
        $lang->jobliste_acp_report_jobs_existing        => $report['jobs_existing'],
        $lang->jobliste_acp_report_skipped_empty        => $report['skipped_empty'],
        $lang->jobliste_acp_report_skipped_orphan_users => $report['skipped_orphan_users'],
        $lang->jobliste_acp_report_unresolved_relatives => count($report['unresolved_relatives'])
            . ($report['unresolved_relatives'] ? '<br /><span class="smalltext">' . htmlspecialchars_uni(implode(', ', array_slice($report['unresolved_relatives'], 0, 100))) . '</span>' : ''),
        $lang->jobliste_acp_report_unresolved_items     => count($report['unresolved_items'])
            . ($report['unresolved_items'] ? '<br /><span class="smalltext">' . htmlspecialchars_uni(implode(', ', $report['unresolved_items'])) . '</span>' : ''),
    ];
    foreach ($rows as $label => $value) {
        $table->construct_cell($label, ['width' => '40%']);
        $table->construct_cell((string)$value);
        $table->construct_row();
    }
    $table->output($lang->jobliste_acp_report_title);
}

// Status
$status = new Table();
$yes_no = static function (bool $ok) use ($lang): string {
    return $ok ? '<span style="color:green">' . $lang->jobliste_acp_available . '</span>' : '<span style="color:#999">' . $lang->jobliste_acp_missing . '</span>';
};
foreach (['relatives_items', 'jobs', 'relatives', 'wanted', 'relatives_persons'] as $table_name) {
    $status->construct_cell('<code>' . TABLE_PREFIX . htmlspecialchars_uni($table_name) . '</code>', ['width' => '40%']);
    $status->construct_cell($yes_no($tables[$table_name]));
    $status->construct_row();
}
$status->construct_cell($lang->jobliste_acp_migration_last_run);
$status->construct_cell($last_run > 0 ? my_date('relative', $last_run) : $lang->jobliste_acp_migration_never);
$status->construct_row();
$status->output($lang->jobliste_acp_migration_status);

if (($tables['relatives'] || $tables['wanted']) && !$tables['relatives_persons']) {
    echo '<div class="error">' . $lang->jobliste_acp_migration_no_relatives . '</div>';
}
if ($last_run > 0) {
    echo '<div class="alert">' . $lang->jobliste_acp_migration_already_done . '</div>';
}

// Formular
$profilefield_options = [0 => $lang->jobliste_acp_migration_no_profilefield];
foreach (Jobliste_Levels::get_selectable_profilefields() as $fid => $name) {
    $profilefield_options[$fid] = $name . ' (#' . $fid . ')';
}

$form = new Form($base_url . '&do=run', 'post');
$form_container = new FormContainer($lang->jobliste_acp_migration_run_title);
$form_container->output_row(
    $lang->jobliste_acp_migration_profilefield,
    $lang->jobliste_acp_migration_profilefield_desc,
    $form->generate_select_box('profilefield_fid', $profilefield_options, [Jobliste_Migration::guess_origin_profilefield()], ['id' => 'profilefield_fid']),
    'profilefield_fid'
);
if ($last_run > 0) {
    $form_container->output_row(
        $lang->jobliste_acp_migration_force,
        $lang->jobliste_acp_migration_force_desc,
        $form->generate_yes_no_radio('force', 0, true),
        'force'
    );
}
$form_container->end();
$form->output_submit_wrapper([$form->generate_submit_button($lang->jobliste_acp_migration_run)]);
$form->end();

$page->output_footer();
