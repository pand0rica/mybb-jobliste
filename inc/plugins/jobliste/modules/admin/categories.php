<?php
/**
 * jobliste ACP - Tab "Kategoriebaum" (add/edit/delete/reorder)
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$do = $mybb->get_input('do');
$id = $mybb->get_input('id', MyBB::INPUT_INT);
$base_url = 'index.php?module=tools-jobliste&action=categories';

$error_messages = [
    Jobliste_Categories::ERR_NOT_FOUND    => $lang->jobliste_acp_not_found,
    Jobliste_Categories::ERR_NAME         => $lang->jobliste_acp_error_name,
    Jobliste_Categories::ERR_PARENT       => $lang->jobliste_acp_error_parent,
    Jobliste_Categories::ERR_CYCLE        => $lang->jobliste_acp_error_cycle,
    Jobliste_Categories::ERR_HAS_CHILDREN => $lang->jobliste_acp_error_has_children,
    Jobliste_Categories::ERR_LEVELS_HAS_CHILDREN => $lang->jobliste_acp_error_levels_has_children,
    Jobliste_Categories::ERR_PARENT_LEVELS => $lang->jobliste_acp_error_parent_levels,
];

$type_labels = [
    'tree'   => $lang->jobliste_acp_type_tree,
    'levels' => $lang->jobliste_acp_type_levels,
];

// --- Ebenen einer levels-Kategorie (do=levels / level_save / level_delete / level_move) ---

if (in_array($do, ['levels', 'level_save', 'level_delete', 'level_move'], true)) {
    require MYBB_ROOT . 'inc/plugins/jobliste/modules/admin/levels.php';
    exit;
}

// --- do=move (POST) -------------------------------------------------------------------

if ($do === 'move' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    Jobliste_Categories::move($id, $mybb->get_input('direction'));
    admin_redirect($base_url . '#cat' . $id);
}

// --- do=delete (POST) -----------------------------------------------------------------

if ($do === 'delete' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    $moved = 0;
    $error = Jobliste_Categories::delete($id, $moved);
    if ($error !== '') {
        flash_message($error_messages[$error] ?? $lang->jobliste_acp_error_generic, 'error');
    } elseif ($moved > 0) {
        flash_message($lang->sprintf($lang->jobliste_acp_category_deleted_moved, $moved), 'success');
    } else {
        flash_message($lang->jobliste_acp_category_deleted, 'success');
    }
    admin_redirect($base_url);
}

// --- do=add / do=edit -----------------------------------------------------------------

if ($do === 'add' || $do === 'edit') {
    $existing = null;
    if ($do === 'edit') {
        $existing = Jobliste_Categories::get($id);
        if ($existing === null) {
            flash_message($lang->jobliste_acp_not_found, 'error');
            admin_redirect($base_url);
        }
    }

    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        $data = [
            'name'              => $mybb->get_input('name'),
            'parent_id'         => $mybb->get_input('parent_id', MyBB::INPUT_INT),
            'allow_custom_text' => $mybb->get_input('allow_custom_text', MyBB::INPUT_INT),
            'is_active'         => $mybb->get_input('is_active', MyBB::INPUT_INT),
            'type'              => $mybb->get_input('type'),
        ];

        if ($do === 'add') {
            if (trim($data['name']) === '') {
                flash_message($lang->jobliste_acp_error_name, 'error');
                admin_redirect($base_url . '&do=add&parent_id=' . (int)$data['parent_id']);
            }
            if ($data['parent_id'] > 0 && Jobliste_Categories::is_levels($data['parent_id'])) {
                flash_message($lang->jobliste_acp_error_parent_levels, 'error');
                admin_redirect($base_url . '&do=add');
            }
            $new_id = Jobliste_Categories::create($data);
            if ($new_id <= 0) {
                flash_message($lang->jobliste_acp_error_parent, 'error');
                admin_redirect($base_url . '&do=add');
            }
            if ($data['type'] === 'levels') {
                // Direkt weiter zur Ebenen-Konfiguration der neuen Kategorie.
                flash_message($lang->jobliste_acp_category_saved_levels, 'success');
                admin_redirect($base_url . '&do=levels&id=' . $new_id);
            }
        } else {
            $error = Jobliste_Categories::update($id, $data);
            if ($error !== '') {
                flash_message($error_messages[$error] ?? $lang->jobliste_acp_error_generic, 'error');
                admin_redirect($base_url . '&do=edit&id=' . $id);
            }
            if ($data['type'] === 'levels' && !Jobliste_Levels::get_levels($id)) {
                flash_message($lang->jobliste_acp_category_saved_levels, 'success');
                admin_redirect($base_url . '&do=levels&id=' . $id);
            }
        }

        flash_message($lang->jobliste_acp_category_saved, 'success');
        admin_redirect($base_url);
    }

    // Elternauswahl: ganzer Baum, beim Bearbeiten ohne die Kategorie selbst und ihre
    // Nachfahren (Zyklenschutz auch schon in der UI).
    $excluded = [];
    if ($existing !== null) {
        $excluded = array_merge([$id], Jobliste_Categories::get_descendant_ids($id));
    }
    $parent_options = [0 => $lang->jobliste_acp_parent_root];
    foreach (Jobliste_Categories::get_flat_tree(false) as $cat) {
        if (in_array($cat['id'], $excluded, true) || $cat['type'] === 'levels') {
            continue; // levels-Kategorien haben keine manuellen Unterkategorien
        }
        $parent_options[$cat['id']] = str_repeat('— ', $cat['depth']) . $cat['name']
            . ($cat['is_active'] === 1 ? '' : ' (' . $lang->jobliste_acp_inactive . ')');
    }

    $page->add_breadcrumb_item($lang->jobliste_tab_categories, $base_url);
    $page->output_header($lang->jobliste_acp_title);
    jobliste_acp_nav_tabs('categories');

    $title = $do === 'add' ? $lang->jobliste_acp_add_category : $lang->jobliste_acp_edit_category;
    $form = new Form($base_url . '&do=' . $do . ($existing ? '&id=' . $id : ''), 'post');
    $form_container = new FormContainer($title);

    $default_parent = $existing['parent_id'] ?? $mybb->get_input('parent_id', MyBB::INPUT_INT);
    $form_container->output_row($lang->jobliste_acp_field_name . ' <em>*</em>', '', $form->generate_text_box('name', $existing['name'] ?? '', ['id' => 'name', 'maxlength' => 255]), 'name');
    $form_container->output_row($lang->jobliste_acp_field_parent, $lang->jobliste_acp_field_parent_desc, $form->generate_select_box('parent_id', $parent_options, [(int)$default_parent], ['id' => 'parent_id']), 'parent_id');
    $form_container->output_row($lang->jobliste_acp_field_type, $lang->jobliste_acp_field_type_desc, $form->generate_select_box('type', $type_labels, [$existing['type'] ?? 'tree'], ['id' => 'type']), 'type');
    $form_container->output_row($lang->jobliste_acp_field_allow_custom_text, $lang->jobliste_acp_field_allow_custom_text_desc, $form->generate_yes_no_radio('allow_custom_text', $existing['allow_custom_text'] ?? 0, true), 'allow_custom_text');
    $form_container->output_row($lang->jobliste_acp_field_is_active, $lang->jobliste_acp_field_is_active_desc, $form->generate_yes_no_radio('is_active', $existing['is_active'] ?? 1, true), 'is_active');
    $form_container->end();

    $buttons = [$form->generate_submit_button($title)];
    $form->output_submit_wrapper($buttons);
    $form->end();

    $page->output_footer();
    exit;
}

// --- Uebersicht ---------------------------------------------------------------------------

$page->add_breadcrumb_item($lang->jobliste_tab_categories, $base_url);
$page->output_header($lang->jobliste_acp_title);
jobliste_acp_nav_tabs('categories');

$actions_table = new Table();
$actions_table->construct_cell(
    '<form action="index.php" method="get" style="margin:0">'
    . '<input type="hidden" name="module" value="tools-jobliste" />'
    . '<input type="hidden" name="action" value="categories" />'
    . '<input type="hidden" name="do" value="add" />'
    . '<input type="submit" class="submit_button" value="' . htmlspecialchars_uni($lang->jobliste_acp_add_category) . '" />'
    . '</form>'
    . '<p class="smalltext" style="margin:6px 0 0">' . $lang->jobliste_acp_add_category_desc . '</p>'
);
$actions_table->construct_row();
$actions_table->output($lang->jobliste_acp_actions_title);

$job_counts = Jobliste_Database::count_jobs_per_category();
$tree = Jobliste_Categories::get_flat_tree(false);
$children_map = Jobliste_Categories::get_children_map();

$table = new Table();
$table->construct_header($lang->jobliste_acp_col_name);
$table->construct_header($lang->jobliste_acp_col_type, ['width' => '18%']);
$table->construct_header($lang->jobliste_acp_col_custom_text, ['class' => 'align_center', 'width' => '8%']);
$table->construct_header($lang->jobliste_acp_col_active, ['class' => 'align_center', 'width' => '8%']);
$table->construct_header($lang->jobliste_acp_col_jobs, ['class' => 'align_center', 'width' => '8%']);
$table->construct_header($lang->jobliste_acp_col_order, ['class' => 'align_center', 'width' => '10%']);
$table->construct_header($lang->jobliste_acp_col_actions, ['class' => 'align_center', 'width' => '25%']);

if (empty($tree)) {
    $table->construct_cell($lang->jobliste_acp_no_categories, ['colspan' => 7]);
    $table->construct_row();
} else {
    foreach ($tree as $cat) {
        $cat_id = $cat['id'];
        $siblings = $children_map[$cat['parent_id']] ?? [];
        $pos = array_search($cat_id, $siblings, true);

        $name = '<a id="cat' . $cat_id . '"></a>' . str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $cat['depth'])
            . ($cat['depth'] > 0 ? '&#8627; ' : '')
            . ($cat['depth'] === 0 ? '<strong>' . htmlspecialchars_uni($cat['name']) . '</strong>' : htmlspecialchars_uni($cat['name']));
        $table->construct_cell($name);
        if ($cat['type'] === 'levels') {
            $level_labels = array_map(static function (array $level): string {
                return htmlspecialchars_uni($level['label']);
            }, Jobliste_Levels::get_levels($cat_id));
            $table->construct_cell(
                $lang->jobliste_acp_type_levels . '<br /><span class="smalltext">'
                . ($level_labels ? implode(' › ', $level_labels) : '<strong>' . $lang->jobliste_acp_levels_none . '</strong>')
                . '</span>'
            );
        } else {
            $table->construct_cell($lang->jobliste_acp_type_tree);
        }
        $table->construct_cell($cat['allow_custom_text'] ? $lang->yes : $lang->no, ['class' => 'align_center']);
        $table->construct_cell($cat['is_active'] ? $lang->yes : '<em>' . $lang->no . '</em>', ['class' => 'align_center']);
        $table->construct_cell((string)($job_counts[$cat_id] ?? 0), ['class' => 'align_center']);

        $order = [];
        if ($pos !== false && $pos > 0) {
            $order[] = jobliste_acp_post_link('▲', $base_url . '&do=move', ['id' => $cat_id, 'direction' => 'up']);
        }
        if ($pos !== false && $pos < count($siblings) - 1) {
            $order[] = jobliste_acp_post_link('▼', $base_url . '&do=move', ['id' => $cat_id, 'direction' => 'down']);
        }
        $table->construct_cell(implode(' ', $order), ['class' => 'align_center']);

        $actions = ['<a href="' . $base_url . '&amp;do=edit&amp;id=' . $cat_id . '">' . $lang->jobliste_acp_edit . '</a>'];
        if ($cat['type'] === 'levels') {
            $actions[] = '<a href="' . $base_url . '&amp;do=levels&amp;id=' . $cat_id . '">' . $lang->jobliste_acp_edit_levels . '</a>';
        } else {
            $actions[] = '<a href="' . $base_url . '&amp;do=add&amp;parent_id=' . $cat_id . '">' . $lang->jobliste_acp_add_child . '</a>';
        }
        if (!$cat['has_children']) {
            $actions[] = jobliste_acp_post_link($lang->jobliste_acp_delete, $base_url . '&do=delete', ['id' => $cat_id], $lang->jobliste_acp_confirm_delete_category);
        }
        $table->construct_cell(implode(' | ', $actions), ['class' => 'align_center']);
        $table->construct_row();
    }
}

$table->output($lang->jobliste_acp_categories_overview);

$page->output_footer();
