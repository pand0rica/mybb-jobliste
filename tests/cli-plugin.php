<?php
/**
 * CLI-Lifecycle-Helper fuer "jobliste". Installiert/deinstalliert das Plugin ohne
 * ACP-Login und beweist den vollstaendigen Cleanup. Spiegelt, was der ACP-Plugin-
 * Manager tut (Lifecycle-Funktionen + plugins-Datacache).
 *
 * Aufruf (auf dem Server, im httpdocs-Root):
 *   php tests/cli-plugin.php install|uninstall|activate|deactivate|status
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only.');
}
define('IN_MYBB', 1);
// Skript liegt in httpdocs/tests/ (oder einem Unterordner davon): MyBB-Root suchen.
$jobliste_root = __DIR__;
while (!file_exists($jobliste_root . "/inc/init.php") && dirname($jobliste_root) !== $jobliste_root) {
    $jobliste_root = dirname($jobliste_root);
}
chdir($jobliste_root);
require_once './inc/init.php';

if (!defined('MYBB_ADMIN_DIR')) {
    $admin_dir = isset($config['admin_dir']) ? $config['admin_dir'] : 'admin';
    define('MYBB_ADMIN_DIR', MYBB_ROOT . $admin_dir . '/');
}
require_once MYBB_ROOT . 'inc/plugins/jobliste.php';

function jobliste_cli_set_active(bool $active): void
{
    global $cache;
    $c = $cache->read('plugins');
    if (!is_array($c)) {
        $c = [];
    }
    if (!isset($c['active']) || !is_array($c['active'])) {
        $c['active'] = [];
    }
    if ($active) {
        $c['active']['jobliste'] = 'jobliste';
    } else {
        unset($c['active']['jobliste']);
    }
    $cache->update('plugins', $c);
}

function jobliste_cli_count(string $table, string $where): int
{
    global $db;
    return (int)$db->fetch_field($db->simple_select($table, 'COUNT(*) AS c', $where), 'c');
}

function jobliste_cli_status(): void
{
    global $db;

    echo "Tables:\n";
    foreach (Jobliste_Installer::TABLES as $table) {
        echo "  {$table}: " . ($db->table_exists($table) ? 'EXISTS' : 'MISSING') . "\n";
    }

    $row = $db->escape_string_like(Jobliste_Installer::usercp_nav_misc_row());
    echo "Settings (name LIKE 'jobliste_%'): " . jobliste_cli_count('settings', "name LIKE 'jobliste\\_%'") . "\n";
    echo "Settinggroup 'jobliste': " . jobliste_cli_count('settinggroups', "name = 'jobliste'") . "\n";
    echo "Templates (title LIKE 'jobliste%'): " . jobliste_cli_count('templates', "title LIKE 'jobliste%'") . "\n";
    echo "Stylesheets (jobliste.css, all themes): " . jobliste_cli_count('themestylesheets', "name = 'jobliste.css'") . "\n";
    echo "usercp.css attached to jobliste.php: " . jobliste_cli_count('themestylesheets', "name = 'usercp.css' AND CONCAT('|', attachedto, '|') LIKE '%|jobliste.php|%'") . "\n";
    echo "usercp_nav_misc templates with menu row: " . jobliste_cli_count('templates', "title = 'usercp_nav_misc' AND template LIKE '%" . $row . "%'") . "\n";
    echo "is_installed(): " . (jobliste_is_installed() ? 'true' : 'false') . "\n";
}

$cmd = isset($argv[1]) ? $argv[1] : 'status';

if ($cmd === 'install') {
    jobliste_install();
    jobliste_activate();
    jobliste_cli_set_active(true);
    echo "Installed + activated.\n";
} elseif ($cmd === 'uninstall') {
    jobliste_deactivate();
    jobliste_uninstall();
    jobliste_cli_set_active(false);
    echo "Deactivated + uninstalled.\n";
} elseif ($cmd === 'activate') {
    jobliste_activate();
    jobliste_cli_set_active(true);
    echo "Activated.\n";
} elseif ($cmd === 'deactivate') {
    jobliste_deactivate();
    jobliste_cli_set_active(false);
    echo "Deactivated.\n";
}

jobliste_cli_status();
