<?php
/**
 * Jobliste - ACP-Tools-Modul
 *
 * Dispatcher fuer die vier Tabs: Kategoriebaum, NPCs, Job-Moderation, Migration.
 * Tab-Handler liegen unter inc/plugins/jobliste/modules/admin/.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$lang->load("jobliste");

require_once MYBB_ROOT . 'inc/plugins/jobliste/core/Categories.php';
require_once MYBB_ROOT . 'inc/plugins/jobliste/core/Levels.php';
require_once MYBB_ROOT . 'inc/plugins/jobliste/core/Database.php';
require_once MYBB_ROOT . 'inc/plugins/jobliste/core/EntityResolver.php';
require_once MYBB_ROOT . 'inc/plugins/jobliste/core/Migration.php';
require_once MYBB_ROOT . 'inc/plugins/jobliste/core/FormFields.php';

$page->add_breadcrumb_item($lang->jobliste, "index.php?module=tools-jobliste");

/**
 * Gemeinsame Tab-Navigation fuer alle ACP-Seiten (globale CLAUDE.md "ACP Page Structure").
 */

function jobliste_acp_nav_tabs(string $active): void
{
    global $page, $lang;

    $tabs = [
        'categories' => [
            'title'       => $lang->jobliste_tab_categories,
            'link'        => 'index.php?module=tools-jobliste&action=categories',
            'description' => $lang->jobliste_tab_desc_categories,
        ],
        'npcs' => [
            'title'       => $lang->jobliste_tab_npcs,
            'link'        => 'index.php?module=tools-jobliste&action=npcs',
            'description' => $lang->jobliste_tab_desc_npcs,
        ],
        'moderation' => [
            'title'       => $lang->jobliste_tab_moderation,
            'link'        => 'index.php?module=tools-jobliste&action=moderation',
            'description' => $lang->jobliste_tab_desc_moderation,
        ],
        'migration' => [
            'title'       => $lang->jobliste_tab_migration,
            'link'        => 'index.php?module=tools-jobliste&action=migration',
            'description' => $lang->jobliste_tab_desc_migration,
        ],
    ];

    $page->output_nav_tabs($tabs, $active);
}

/**
 * Textlink, der ein verstecktes POST-Formular absendet (Loeschen, Sortieren) -- so sehen
 * alle Zeilenaktionen gleich aus, schreibende Aktionen bleiben POST + verify_post_check().
 *
 * @param array<string, string|int> $fields versteckte Felder (ohne my_post_key)
 */
function jobliste_acp_post_link(string $label, string $post_url, array $fields, string $confirm = ''): string
{
    global $mybb;

    static $counter = 0;
    $counter++;
    $form_id = 'jobliste_post_' . $counter;

    $html = '<form id="' . $form_id . '" action="' . htmlspecialchars_uni($post_url) . '" method="post" style="display:none">';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . htmlspecialchars_uni((string)$name) . '" value="' . htmlspecialchars_uni((string)$value) . '" />';
    }
    $html .= '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" /></form>';

    $submit = "document.getElementById('" . $form_id . "').submit();";
    if ($confirm !== '') {
        // json_encode yields a valid JS string literal; the browser decodes the entities
        // before running the JS.
        $submit = "if(confirm(" . htmlspecialchars_uni((string)json_encode($confirm)) . ")){" . $submit . "}";
    }
    return $html . '<a href="javascript:void(0)" onclick="' . $submit . 'return false;">' . htmlspecialchars_uni($label) . '</a>';
}

/** Kategoriepfad + Freitext eines Berufs als HTML (escaped). */
function jobliste_acp_job_html(array $job): string
{
    global $lang;

    $parts = [];
    $path = $job['category_id'] !== null ? Jobliste_Categories::get_path_label((int)$job['category_id']) : '';
    // Level values (category type "levels") belong to the path, like in the frontend.
    $segments = array_merge($path !== '' ? [$path] : [], $job['level_values']);
    if ($segments) {
        $parts[] = htmlspecialchars_uni(implode(Jobliste_Categories::PATH_SEPARATOR, $segments));
    }
    if ($job['custom_label'] !== null && $job['custom_label'] !== '') {
        $parts[] = '<em>' . htmlspecialchars_uni($job['custom_label']) . '</em>';
    }
    return $parts ? implode(' &ndash; ', $parts) : $lang->jobliste_acp_no_category;
}

/** Typ-Beschriftung einer Entitaet (Rohtext). */
function jobliste_acp_type_label(string $type): string
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

$action = $mybb->get_input('action');
$allowed_actions = ['categories', 'npcs', 'moderation', 'migration'];
if (!in_array($action, $allowed_actions, true)) {
    $action = 'categories';
}

require_once MYBB_ROOT . 'inc/plugins/jobliste/modules/admin/' . $action . '.php';
