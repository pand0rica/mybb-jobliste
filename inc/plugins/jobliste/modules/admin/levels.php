<?php
/**
 * jobliste ACP - Ebenen einer Kategorie vom Typ "levels" (Unterseite des Tabs
 * "Kategoriebaum"). Wird aus modules/admin/categories.php eingebunden; $do, $id,
 * $base_url und $type_labels kommen von dort.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$category = Jobliste_Categories::get($id);
if ($category === null) {
    flash_message($lang->jobliste_acp_not_found, 'error');
    admin_redirect($base_url);
}
$levels_url = $base_url . '&do=levels&id=' . $id;
$level_id = $mybb->get_input('level_id', MyBB::INPUT_INT);

// Gehoert die Ebene wirklich zu dieser Kategorie?
$level = $level_id > 0 ? Jobliste_Levels::get_level($level_id) : null;
if ($level !== null && $level['category_id'] !== $id) {
    $level = null;
}

// --- level_move / level_delete / level_save (POST) ------------------------------------

if ($do === 'level_move' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    if ($level !== null) {
        Jobliste_Levels::move($level['id'], $mybb->get_input('direction'));
    }
    admin_redirect($levels_url);
}

if ($do === 'level_delete' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    if ($level === null) {
        flash_message($lang->jobliste_acp_not_found, 'error');
    } else {
        Jobliste_Levels::delete($level['id']);
        flash_message($lang->jobliste_acp_level_deleted, 'success');
    }
    admin_redirect($levels_url);
}

if ($do === 'level_save' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    $data = [
        'label'            => $mybb->get_input('label'),
        'source'           => $mybb->get_input('source'),
        'profilefield_fid' => $mybb->get_input('profilefield_fid', MyBB::INPUT_INT),
        'options'          => $mybb->get_input('options'),
    ];
    $ok = $level !== null
        ? Jobliste_Levels::update($level['id'], $data)
        : Jobliste_Levels::create($id, $data) > 0;
    if (!$ok) {
        flash_message($lang->jobliste_acp_error_level, 'error');
        admin_redirect($levels_url . ($level !== null ? '&level_id=' . $level['id'] : ''));
    }
    flash_message($lang->jobliste_acp_level_saved, 'success');
    admin_redirect($levels_url);
}

// --- Seite: Liste + Formular ---------------------------------------------------------------

$page->add_breadcrumb_item($lang->jobliste_tab_categories, $base_url);
$page->add_breadcrumb_item(htmlspecialchars_uni($category['name']), $levels_url);
$page->output_header($lang->jobliste_acp_title);
jobliste_acp_nav_tabs('categories');

if ($category['type'] !== 'levels') {
    echo '<div class="alert">' . $lang->jobliste_acp_levels_not_levels_type . '</div>';
}

$profilefields = Jobliste_Levels::get_selectable_profilefields();
$source_labels = [
    'profilefield' => $lang->jobliste_acp_source_profilefield,
    'list'         => $lang->jobliste_acp_source_list,
];

$table = new Table();
$table->construct_header($lang->jobliste_acp_col_level, ['width' => '20%']);
$table->construct_header($lang->jobliste_acp_col_source, ['width' => '25%']);
$table->construct_header($lang->jobliste_acp_col_options);
$table->construct_header($lang->jobliste_acp_col_order, ['class' => 'align_center', 'width' => '8%']);
$table->construct_header($lang->jobliste_acp_col_actions, ['class' => 'align_center', 'width' => '15%']);

$levels = Jobliste_Levels::get_levels($id);
if (!$levels) {
    $table->construct_cell($lang->jobliste_acp_levels_none, ['colspan' => 5]);
    $table->construct_row();
}
foreach ($levels as $index => $row) {
    $options = Jobliste_Levels::get_options($row);
    $source = $source_labels[$row['source']] ?? $row['source'];
    if ($row['source'] === 'profilefield') {
        $source .= ': ' . htmlspecialchars_uni($profilefields[$row['profilefield_fid']] ?? ('#' . $row['profilefield_fid'] . ' – ' . $lang->jobliste_acp_profilefield_missing));
    }
    $preview = array_map('htmlspecialchars_uni', array_slice($options, 0, 12));
    $preview_text = $preview ? implode(', ', $preview) . (count($options) > 12 ? ', …' : '') : '<em>' . $lang->jobliste_acp_level_no_options . '</em>';

    $order = [];
    if ($index > 0) {
        $order[] = jobliste_acp_post_link('▲', $base_url . '&do=level_move&id=' . $id, ['level_id' => $row['id'], 'direction' => 'up']);
    }
    if ($index < count($levels) - 1) {
        $order[] = jobliste_acp_post_link('▼', $base_url . '&do=level_move&id=' . $id, ['level_id' => $row['id'], 'direction' => 'down']);
    }

    $table->construct_cell('<strong>' . ($index + 1) . '. ' . htmlspecialchars_uni($row['label']) . '</strong>');
    $table->construct_cell($source);
    $table->construct_cell('<span class="smalltext">' . $preview_text . ' (' . count($options) . ')</span>');
    $table->construct_cell(implode(' ', $order), ['class' => 'align_center']);
    $table->construct_cell(
        '<a href="' . htmlspecialchars_uni($levels_url) . '&amp;level_id=' . $row['id'] . '#level_form">' . $lang->jobliste_acp_edit . '</a> | '
        . jobliste_acp_post_link($lang->jobliste_acp_delete, $base_url . '&do=level_delete&id=' . $id, ['level_id' => $row['id']], $lang->jobliste_acp_confirm_delete_level),
        ['class' => 'align_center']
    );
    $table->construct_row();
}
$table->output($lang->sprintf($lang->jobliste_acp_levels_title, htmlspecialchars_uni(Jobliste_Categories::get_path_label($id))));

// Formular: neue Ebene bzw. Ebene bearbeiten
$title = $level !== null ? $lang->jobliste_acp_edit_level : $lang->jobliste_acp_add_level;
echo '<a id="level_form"></a>';
$form = new Form($base_url . '&do=level_save&id=' . $id . ($level !== null ? '&level_id=' . $level['id'] : ''), 'post');
$form_container = new FormContainer($title);
$form_container->output_row($lang->jobliste_acp_field_level_label . ' <em>*</em>', $lang->jobliste_acp_field_level_label_desc, $form->generate_text_box('label', $level['label'] ?? '', ['id' => 'label', 'maxlength' => 100]), 'label');
$form_container->output_row($lang->jobliste_acp_field_source, $lang->jobliste_acp_field_source_desc, $form->generate_select_box('source', $source_labels, [$level['source'] ?? 'list'], ['id' => 'source']), 'source');
$profilefield_options = [0 => $lang->jobliste_acp_profilefield_choose];
foreach ($profilefields as $fid => $name) {
    $profilefield_options[$fid] = $name . ' (#' . $fid . ')';
}
$form_container->output_row($lang->jobliste_acp_field_profilefield, $lang->jobliste_acp_field_profilefield_desc, $form->generate_select_box('profilefield_fid', $profilefield_options, [$level['profilefield_fid'] ?? 0], ['id' => 'profilefield_fid']), 'profilefield_fid');
$form_container->output_row($lang->jobliste_acp_field_options, $lang->jobliste_acp_field_options_desc, $form->generate_text_area('options', $level['options'] ?? '', ['id' => 'options', 'rows' => 8]), 'options');
$form_container->end();
$form->output_submit_wrapper([$form->generate_submit_button($title)]);
$form->end();

$page->output_footer();
