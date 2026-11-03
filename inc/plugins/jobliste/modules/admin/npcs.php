<?php
/**
 * jobliste ACP - Tab "NPCs verwalten"
 *
 * Anlegen/Bearbeiten eines NPCs inkl. seiner Berufe in einem Formular: je bestehendem
 * Beruf ein Feldblock ("kein Beruf" entfernt ihn), dazu ein optionaler Block fuer einen
 * neuen Beruf (solange das Limit pro Person nicht erreicht ist).
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$do = $mybb->get_input('do');
$id = $mybb->get_input('id', MyBB::INPUT_INT);
$base_url = 'index.php?module=tools-jobliste&action=npcs';

/**
 * Field block for one job (category combo, levels, free text), scoped for jobliste.js via
 * data-jobliste-scope so several blocks can live in one form. Input names carry $prefix.
 *
 * @param string[] $level_values
 */
function jobliste_acp_job_fields(string $prefix, int $selected_category, array $level_values, string $custom_label): string
{
    global $lang;

    $none = htmlspecialchars_uni($lang->jobliste_acp_no_job_option);
    $options = '<option value="0" data-path="' . $none . '"' . ($selected_category === 0 ? ' selected="selected"' : '') . '>' . $none . '</option>'
        . jobliste_category_options($selected_category);
    $level_fields = jobliste_level_fields($selected_category, $level_values, $prefix);
    $placeholder = htmlspecialchars_uni($lang->jobliste_search_placeholder);

    $html = '<div class="jobliste_acp_job" data-jobliste-scope="1">'
        . '<div class="jobliste_combo" data-jobliste-combo="1">'
        . '<input type="search" class="text_input jobliste_combo_search" placeholder="' . $placeholder . '" aria-label="' . $placeholder . '" autocomplete="off" hidden="hidden" />'
        . '<select name="' . $prefix . 'category_id" id="' . $prefix . 'category_id" class="jobliste_combo_select" size="8">' . $options . '</select>'
        . '<div class="jobliste_combo_selected smalltext" aria-live="polite"></div>'
        . '</div>';
    if ($level_fields !== '') {
        $html .= '<div class="jobliste_levels_row">'
            . '<span class="jobliste_acp_sublabel">' . htmlspecialchars_uni($lang->jobliste_field_levels) . '</span>'
            . '<div class="smalltext">' . htmlspecialchars_uni($lang->jobliste_field_levels_desc) . '</div>'
            . '<div class="jobliste_levels">' . $level_fields . '</div>'
            . '</div>';
    }
    $html .= '<div class="jobliste_custom_row">'
        . '<label class="jobliste_acp_sublabel" for="' . $prefix . 'custom_label">' . htmlspecialchars_uni($lang->jobliste_field_custom_label) . '</label>'
        . '<div class="smalltext">' . htmlspecialchars_uni($lang->jobliste_field_custom_label_desc) . '</div>'
        . '<input type="text" name="' . $prefix . 'custom_label" id="' . $prefix . 'custom_label" class="text_input jobliste_custom_input" maxlength="' . Jobliste_Database::MAX_LABEL . '" value="' . htmlspecialchars_uni($custom_label) . '" />'
        . '</div>'
        . '</div>';
    return $html;
}

/**
 * Posted values of a job block for re-rendering after a failed save.
 *
 * @return array [category_id, level_values, custom_label]
 */
function jobliste_acp_posted_job(string $prefix): array
{
    global $mybb;

    $category_id = $mybb->get_input($prefix . 'category_id', MyBB::INPUT_INT);
    $levels = $mybb->get_input($prefix . 'level', MyBB::INPUT_ARRAY);
    $values = isset($levels[$category_id]) && is_array($levels[$category_id])
        ? array_map('strval', array_filter($levels[$category_id], 'is_scalar'))
        : [];
    return [$category_id, $values, $mybb->get_input($prefix . 'custom_label')];
}

// --- do=delete (POST) -----------------------------------------------------------------

if ($do === 'delete' && $mybb->request_method === 'post') {
    jobliste_acp_verify_key();
    if (Jobliste_Database::get_npc($id) === null) {
        flash_message($lang->jobliste_acp_not_found, 'error');
    } else {
        Jobliste_Database::delete_npc($id);
        flash_message($lang->jobliste_acp_npc_deleted, 'success');
    }
    admin_redirect($base_url);
}

// --- do=add / do=edit -----------------------------------------------------------------

if ($do === 'add' || $do === 'edit') {
    // Field labels, error texts and the level placeholder live in the frontend language
    // file (shared with FormFields.php).
    $lang->load('jobliste', true);

    $existing = null;
    $jobs = [];
    if ($do === 'edit') {
        $existing = Jobliste_Database::get_npc($id);
        if ($existing === null) {
            flash_message($lang->jobliste_acp_not_found, 'error');
            admin_redirect($base_url);
        }
        $jobs = Jobliste_Database::get_jobs_by_entity('npc', $id);
    }
    $max_jobs = (int)($mybb->settings['jobliste_max_jobs_per_entity'] ?? 0);
    $can_add_job = $max_jobs <= 0 || count($jobs) < $max_jobs;

    $errors = [];
    if ($mybb->request_method === 'post') {
        jobliste_acp_verify_key();

        $name = $mybb->get_input('name');
        $notes = $mybb->get_input('notes');
        if (Jobliste_Database::clean_label($name) === null) {
            $errors[] = $lang->jobliste_acp_error_npc_name;
        }

        // Validate every block first, write only if everything is valid.
        $job_updates = [];
        $job_deletes = [];
        foreach ($jobs as $job) {
            $prefix = 'job' . (int)$job['id'] . '_';
            if ($mybb->get_input($prefix . 'category_id', MyBB::INPUT_INT) <= 0) {
                $job_deletes[] = (int)$job['id'];
                continue;
            }
            [$error, $category_id, $custom_label, $level_values] = jobliste_validate_job_input($job['category_id'], $job['level_values'], $prefix);
            if ($error !== '') {
                $errors[] = $error;
                continue;
            }
            $job_updates[(int)$job['id']] = [$category_id, $custom_label, $level_values];
        }
        $new_job = null;
        if ($can_add_job && $mybb->get_input('jobnew_category_id', MyBB::INPUT_INT) > 0) {
            [$error, $category_id, $custom_label, $level_values] = jobliste_validate_job_input(null, [], 'jobnew_');
            if ($error !== '') {
                $errors[] = $error;
            } else {
                $new_job = [$category_id, $custom_label, $level_values];
            }
        }

        if (!$errors) {
            if ($existing === null) {
                $id = Jobliste_Database::insert_npc($name, $notes, (int)$mybb->user['uid']);
            } else {
                Jobliste_Database::update_npc($id, $name, $notes);
            }
            if ($id > 0) {
                foreach ($job_deletes as $job_id) {
                    Jobliste_Database::delete_job($job_id);
                }
                foreach ($job_updates as $job_id => [$category_id, $custom_label, $level_values]) {
                    Jobliste_Database::update_job($job_id, $category_id, $custom_label, $level_values);
                }
                if ($new_job !== null) {
                    Jobliste_Database::insert_job([
                        'entity_type'    => 'npc',
                        'entity_id'      => $id,
                        'category_id'    => $new_job[0],
                        'custom_label'   => $new_job[1],
                        'level_values'   => $new_job[2],
                        'created_by_uid' => (int)$mybb->user['uid'],
                    ]);
                }
                flash_message($lang->jobliste_acp_npc_saved, 'success');
                admin_redirect($base_url);
            }
            $errors[] = $lang->jobliste_error_save;
        }
    }
    $is_post = $mybb->request_method === 'post';

    // Same stylesheet/script as the frontend form: searchable category combo, free text
    // and level fields shown only where the chosen category allows them.
    $asset_version = (int)(@filemtime(MYBB_ROOT . 'inc/plugins/jobliste/assets/jobliste.js') ?: TIME_NOW);
    $page->extra_header .= '<link rel="stylesheet" href="../inc/plugins/jobliste/assets/jobliste.css?v=' . $asset_version . '" />'
        . '<script src="../inc/plugins/jobliste/assets/jobliste.js?v=' . $asset_version . '"></script>';

    $title = $do === 'add' ? $lang->jobliste_acp_add_npc : $lang->jobliste_acp_edit_npc;
    $page->add_breadcrumb_item($lang->jobliste_tab_npcs, $base_url);
    $page->add_breadcrumb_item($title);
    $page->output_header($lang->jobliste_acp_title);
    jobliste_acp_nav_tabs('npcs');

    if ($errors) {
        $page->output_inline_error($errors);
    }

    $name_value = $is_post ? $mybb->get_input('name') : ($existing['name'] ?? '');
    $notes_value = $is_post ? $mybb->get_input('notes') : ($existing['notes'] ?? '');

    $form = new Form($base_url . '&do=' . $do . ($existing ? '&id=' . $id : ''), 'post');
    $form_container = new FormContainer($title);
    $form_container->output_row($lang->jobliste_acp_field_npc_name . ' <em>*</em>', '', $form->generate_text_box('name', $name_value, ['id' => 'name', 'maxlength' => 255]), 'name');
    $form_container->output_row($lang->jobliste_acp_field_npc_notes, $lang->jobliste_acp_field_npc_notes_desc, $form->generate_text_area('notes', $notes_value, ['id' => 'notes']), 'notes');

    $number = 0;
    foreach ($jobs as $job) {
        $number++;
        $prefix = 'job' . (int)$job['id'] . '_';
        [$category_id, $level_values, $custom_label] = $is_post
            ? jobliste_acp_posted_job($prefix)
            : [(int)$job['category_id'], $job['level_values'], (string)$job['custom_label']];
        $form_container->output_row(
            $lang->sprintf($lang->jobliste_acp_field_job_n, $number),
            $lang->jobliste_acp_field_job_existing_desc,
            jobliste_acp_job_fields($prefix, $category_id, $level_values, $custom_label),
            $prefix . 'category_id'
        );
    }
    if ($can_add_job) {
        [$category_id, $level_values, $custom_label] = $is_post ? jobliste_acp_posted_job('jobnew_') : [0, [], ''];
        $form_container->output_row(
            $jobs ? $lang->jobliste_acp_field_job_new : $lang->jobliste_acp_field_job,
            $lang->jobliste_acp_field_job_desc,
            jobliste_acp_job_fields('jobnew_', $category_id, $level_values, $custom_label),
            'jobnew_category_id'
        );
    } else {
        $form_container->output_row($lang->jobliste_acp_field_job_new, '', $lang->sprintf($lang->jobliste_error_limit, $max_jobs));
    }
    $form_container->end();

    $form->output_submit_wrapper([$form->generate_submit_button($title)]);
    $form->end();

    $page->output_footer();
    exit;
}

// --- Uebersicht ---------------------------------------------------------------------------

$page->add_breadcrumb_item($lang->jobliste_tab_npcs, $base_url);
$page->output_header($lang->jobliste_acp_title);
jobliste_acp_nav_tabs('npcs');

$actions_table = new Table();
$actions_table->construct_cell(
    '<form action="index.php" method="get" style="margin:0">'
    . '<input type="hidden" name="module" value="tools-jobliste" />'
    . '<input type="hidden" name="action" value="npcs" />'
    . '<input type="hidden" name="do" value="add" />'
    . '<input type="submit" class="submit_button" value="' . htmlspecialchars_uni($lang->jobliste_acp_add_npc) . '" />'
    . '</form>'
    . '<p class="smalltext" style="margin:6px 0 0">' . $lang->jobliste_acp_add_npc_desc . '</p>'
);
$actions_table->construct_row();
$actions_table->output($lang->jobliste_acp_actions_title);

$npcs = Jobliste_Database::get_npcs();
$entities = [];
foreach ($npcs as $npc) {
    $entities[] = ['entity_type' => 'npc', 'entity_id' => $npc['id']];
}
$jobs_by_npc = [];
foreach (Jobliste_Database::get_jobs_by_entities($entities) as $job) {
    $jobs_by_npc[$job['entity_id']][] = $job;
}

$table = new Table();
$table->construct_header($lang->jobliste_acp_field_npc_name, ['width' => '20%']);
$table->construct_header($lang->jobliste_acp_field_npc_notes, ['width' => '25%']);
$table->construct_header($lang->jobliste_acp_col_jobs);
$table->construct_header($lang->jobliste_acp_col_created_by, ['width' => '12%']);
$table->construct_header($lang->jobliste_acp_col_actions, ['class' => 'align_center', 'width' => '15%']);

if (empty($npcs)) {
    $table->construct_cell($lang->jobliste_acp_no_npcs, ['colspan' => 5]);
    $table->construct_row();
} else {
    $creator_names = [];
    $creator_ids = array_filter(array_unique(array_column($npcs, 'created_by_uid')));
    if ($creator_ids) {
        $query = $db->simple_select('users', 'uid, username', 'uid IN (' . implode(',', array_map('intval', $creator_ids)) . ')');
        while ($row = $db->fetch_array($query)) {
            $creator_names[(int)$row['uid']] = (string)$row['username'];
        }
    }

    foreach ($npcs as $npc) {
        $jobs_html = [];
        foreach ($jobs_by_npc[$npc['id']] ?? [] as $job) {
            $jobs_html[] = jobliste_acp_job_html($job);
        }
        $table->construct_cell('<strong>' . htmlspecialchars_uni($npc['name']) . '</strong>');
        $table->construct_cell(nl2br(htmlspecialchars_uni($npc['notes'])), ['class' => 'smalltext']);
        $table->construct_cell($jobs_html ? implode('<br />', $jobs_html) : '<span class="smalltext">' . $lang->jobliste_acp_no_jobs . '</span>');
        $table->construct_cell(isset($creator_names[$npc['created_by_uid']]) ? htmlspecialchars_uni($creator_names[$npc['created_by_uid']]) : '&ndash;');
        $table->construct_cell(
            '<a href="' . $base_url . '&amp;do=edit&amp;id=' . $npc['id'] . '">' . $lang->jobliste_acp_edit . '</a> | '
            . jobliste_acp_post_link($lang->jobliste_acp_delete, $base_url . '&do=delete', ['id' => $npc['id']], $lang->jobliste_acp_confirm_delete_npc),
            ['class' => 'align_center']
        );
        $table->construct_row();
    }
}

$table->output($lang->jobliste_acp_npcs_overview);

$page->output_footer();
