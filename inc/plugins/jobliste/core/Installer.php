<?php
/**
 * jobliste - Installer
 *
 * Kapselt die komplette Lifecycle-Logik (install/uninstall/activate/deactivate).
 * Wird ausschliesslich von den globalen jobliste_install()/jobliste_activate()/...
 * Funktionen in inc/plugins/jobliste.php aufgerufen (MyBB ruft nur diese globalen
 * Funktionsnamen selbst auf).
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

class Jobliste_Installer
{
    /** Alle Plugin-Tabellen ohne TABLE_PREFIX (fuer table_exists()/drop_table()). */
    public const TABLES = [
        'jobliste_categories',
        'jobliste_category_levels',
        'jobliste_jobs',
        'jobliste_npcs',
        'jobliste_requests',
    ];

    /** Stylesheet-Name in themestylesheets. */
    private const STYLESHEET = 'jobliste.css';

    /** Frontend-Skript, an das usercp.css zusaetzlich gehaengt wird. */
    private const SCRIPT = 'jobliste.php';

    /**
     * Seiten, an die jobliste.css haengt: eigene Seite + Personenformular des
     * Relatives-Plugins (Beruf-Felder, Hook relatives_person_form_fields).
     */
    private const STYLESHEET_ATTACHED = 'jobliste.php|relatives.php';

    public static function install(): void
    {
        global $db;

        $collation = " DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

        // Kategoriebaum (adjacency list). allow_custom_text ersetzt die alte Magic-Number
        // next=2; "hat Kinder?" wird per Query abgeleitet, nicht gespeichert.
        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "jobliste_categories` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `parent_id` INT(11) NOT NULL DEFAULT 0,
            `name` VARCHAR(255) NOT NULL DEFAULT '',
            `allow_custom_text` TINYINT(1) NOT NULL DEFAULT 0,
            `type` ENUM('tree','levels') NOT NULL DEFAULT 'tree',
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `legacy_item_id` INT(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `parent_id` (`parent_id`),
            KEY `legacy_item_id` (`legacy_item_id`)
        ) ENGINE=InnoDB{$collation};");

        // Ein Berufseintrag. entity_id zeigt je nach entity_type auf users.uid,
        // relatives_persons.id (nur ueber Provider-Hooks aufgeloest) oder jobliste_npcs.id.
        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "jobliste_jobs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `entity_type` ENUM('user','relative','npc') NOT NULL DEFAULT 'user',
            `entity_id` INT(11) NOT NULL DEFAULT 0,
            `category_id` INT(11) DEFAULT NULL,
            `custom_label` VARCHAR(255) DEFAULT NULL,
            `level_values` TEXT,
            `created_by_uid` INT(11) NOT NULL DEFAULT 0,
            `legacy_job_id` INT(11) DEFAULT NULL,
            `created_at` INT(11) NOT NULL DEFAULT 0,
            `updated_at` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `entity` (`entity_type`, `entity_id`),
            KEY `category_id` (`category_id`),
            KEY `created_by_uid` (`created_by_uid`),
            KEY `legacy_job_id` (`legacy_job_id`)
        ) ENGINE=InnoDB{$collation};");

        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "jobliste_npcs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL DEFAULT '',
            `notes` TEXT,
            `created_by_uid` INT(11) NOT NULL DEFAULT 0,
            `created_at` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `name` (`name`(191))
        ) ENGINE=InnoDB{$collation};");

        self::create_levels_table();
        self::create_requests_table();

        // Settings sind Nutzerdaten -> install()/uninstall(), nicht activate()/deactivate()
        // (debugging.md Punkt 21).
        self::install_settings();
        rebuild_settings();
    }

    public static function is_installed(): bool
    {
        global $db;
        return $db->table_exists('jobliste_jobs');
    }

    public static function uninstall(): void
    {
        global $db;

        foreach (self::TABLES as $table) {
            if ($db->table_exists($table)) {
                $db->drop_table($table);
            }
        }

        $db->delete_query('settings', "name LIKE 'jobliste_%'");
        $db->delete_query('settinggroups', "name = 'jobliste'");
        rebuild_settings();
    }

    public static function activate(): void
    {
        self::upgrade_schema();
        // Idempotent: ruestet bei Bestandsinstallationen neue Settings spaeterer
        // Versionen nach, ohne vorhandene Werte anzufassen.
        self::install_settings();
        self::install_templates();
        self::install_stylesheet();
        self::attach_usercp_stylesheet();
        self::install_usercp_nav_misc_link();
        self::install_index_notice();
        self::install_modcp_nav();

        rebuild_settings();
    }

    public static function deactivate(): void
    {
        global $db;

        require_once MYBB_ROOT . "inc/adminfunctions_templates.php";
        require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

        $db->delete_query('templates', "title LIKE 'jobliste%'");

        // Settings + Gruppe bleiben erhalten (siehe uninstall()).

        $db->delete_query('themestylesheets', "name = '" . self::STYLESHEET . "'");
        self::detach_usercp_stylesheet();
        self::uninstall_usercp_nav_misc_link();
        self::uninstall_index_notice();
        self::uninstall_modcp_nav();
        $themes = $db->simple_select('themes', 'tid');
        while ($theme = $db->fetch_array($themes)) {
            update_theme_stylesheet_list((int)$theme['tid']);
        }

        rebuild_settings();
    }

    // --- Schema ---------------------------------------------------------------------

    /**
     * Ebenen-Definitionen fuer Kategorien vom Typ "levels" (PROJECT_CONTEXT.md
     * "Kategorietypen"). source=profilefield: Optionen des Profilfelds profilefield_fid;
     * source=list: Werte in options, einer pro Zeile.
     */
    private static function create_levels_table(): void
    {
        global $db;

        $collation = " DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "jobliste_category_levels` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `category_id` INT(11) NOT NULL DEFAULT 0,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `label` VARCHAR(100) NOT NULL DEFAULT '',
            `source` ENUM('profilefield','list') NOT NULL DEFAULT 'list',
            `profilefield_fid` INT(11) NOT NULL DEFAULT 0,
            `options` TEXT,
            PRIMARY KEY (`id`),
            KEY `category_id` (`category_id`)
        ) ENGINE=InnoDB{$collation};");
    }

    /**
     * Kategorie-Antraege aus dem UserCP (Jobliste_Requests). parent_id 0 = neue
     * Hauptkategorie; category_id = beim Annehmen angelegte Kategorie.
     */
    private static function create_requests_table(): void
    {
        global $db;

        $collation = " DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "jobliste_requests` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `uid` INT(11) NOT NULL DEFAULT 0,
            `parent_id` INT(11) NOT NULL DEFAULT 0,
            `name` VARCHAR(255) NOT NULL DEFAULT '',
            `note` TEXT,
            `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            `category_id` INT(11) NOT NULL DEFAULT 0,
            `response` TEXT,
            `handled_by_uid` INT(11) NOT NULL DEFAULT 0,
            `handled_at` INT(11) NOT NULL DEFAULT 0,
            `created_at` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `status` (`status`),
            KEY `uid` (`uid`)
        ) ENGINE=InnoDB{$collation};");
    }

    /**
     * Idempotentes Nachruesten fuer Installationen, die vor dem Kategorietyp "Ebenen"
     * angelegt wurden (field_exists()-Guards, darf beliebig oft laufen).
     */
    private static function upgrade_schema(): void
    {
        global $db;

        if (!$db->table_exists('jobliste_categories')) {
            return;
        }
        self::create_levels_table();
        self::create_requests_table();
        if (!$db->field_exists('type', 'jobliste_categories')) {
            $db->write_query("ALTER TABLE `" . TABLE_PREFIX . "jobliste_categories`
                ADD COLUMN `type` ENUM('tree','levels') NOT NULL DEFAULT 'tree' AFTER `allow_custom_text`");
        }
        if ($db->table_exists('jobliste_jobs') && !$db->field_exists('level_values', 'jobliste_jobs')) {
            $db->write_query("ALTER TABLE `" . TABLE_PREFIX . "jobliste_jobs`
                ADD COLUMN `level_values` TEXT AFTER `custom_label`");
        }
    }

    // --- Settings -------------------------------------------------------------------

    private static function install_settings(): void
    {
        global $db;

        $existing = $db->fetch_array($db->simple_select('settinggroups', 'gid', "name = 'jobliste'"));
        if ($existing) {
            $gid = (int)$existing['gid'];
        } else {
            $gid = (int)$db->insert_query('settinggroups', [
                'name'        => 'jobliste',
                'title'       => 'Jobliste',
                'description' => 'Einstellungen fuer die Jobliste (Berufe von Charakteren, Verwandten und NPCs).',
                'disporder'   => 1,
                'isdefault'   => 0,
            ]);
        }

        $settings = [
            [
                'name'        => 'jobliste_enabled',
                'title'       => 'Jobliste aktiv',
                'description' => 'Master-Schalter. Aus = jobliste.php zeigt nur einen Hinweis.',
                'optionscode' => 'onoff',
                'value'       => '1',
                'disporder'   => 1,
            ],
            [
                'name'        => 'jobliste_team_usergroups',
                'title'       => 'Team-Gruppen',
                'description' => 'Mitglieder dieser Gruppen duerfen NPC-Berufe anlegen und fremde Eintraege bearbeiten/loeschen.',
                'optionscode' => 'groupselect',
                'value'       => '4',
                'disporder'   => 2,
            ],
            [
                'name'        => 'jobliste_relatives_integration',
                'title'       => 'Verwandten-Integration',
                'description' => 'Berufe auch fuer Verwandte (Relatives-Plugin) erlauben. Aus = Verwandte werden ignoriert, auch wenn das Relatives-Plugin aktiv ist.',
                'optionscode' => 'onoff',
                'value'       => '1',
                'disporder'   => 3,
            ],
            [
                'name'        => 'jobliste_max_jobs_per_entity',
                'title'       => 'Maximale Berufe pro Person',
                'description' => 'Missbrauchsschutz: wie viele Berufe eine Person (Charakter, Verwandte:r oder NPC) hoechstens haben darf. 0 = unbegrenzt.',
                'optionscode' => 'numeric',
                'value'       => '0',
                'disporder'   => 4,
            ],
            [
                'name'        => 'jobliste_legacy_migrated',
                'title'       => 'Letzte Legacy-Migration (Unix-Zeitstempel)',
                'description' => 'Wird automatisch vom Migrations-Tool gesetzt. 0 = noch nie migriert. Nicht manuell aendern.',
                'optionscode' => 'text',
                'value'       => '0',
                'disporder'   => 5,
            ],
            [
                'name'        => 'jobliste_overview_guests',
                'title'       => 'Gesamtliste fuer Gaeste',
                'description' => 'Duerfen Gaeste die Gesamtliste aller Berufe (jobliste.php?action=overview) sehen? Die Gruppenberechtigung "Forum ansehen" gilt zusaetzlich.',
                'optionscode' => 'yesno',
                'value'       => '1',
                'disporder'   => 6,
            ],
            [
                'name'        => 'jobliste_overview_layout_template',
                'title'       => 'Gesamtliste: Rahmen-Template (optional)',
                'description' => 'Name eines vorhandenen Templates, in das die Gesamtliste eingebettet wird, z. B. der Rahmen eurer uebrigen Listen ("listen"). Es erhaelt den Inhalt als {$listen_content} bzw. {$jobliste_overview_content}, die Navigation als {$listen_nav} bzw. {$jobliste_list_nav} und den Titel als {$headline}. Leer = eigene Seite des Plugins.',
                'optionscode' => 'text',
                'value'       => '',
                'disporder'   => 7,
            ],
            [
                'name'        => 'jobliste_overview_nav_template',
                'title'       => 'Gesamtliste: Navigations-Template (optional)',
                'description' => 'Name eines vorhandenen Templates mit der Listen-Navigation, z. B. "listen_nav". Nur zusammen mit dem Rahmen-Template wirksam.',
                'optionscode' => 'text',
                'value'       => '',
                'disporder'   => 8,
            ],
            [
                'name'        => 'jobliste_requests_enabled',
                'title'       => 'Kategorie-Antraege',
                'description' => 'Duerfen Nutzer:innen im UserCP neue Kategorien/Arbeitsorte beantragen? Das Team (Team-Gruppen mit ModCP-Zugang) sieht offene Antraege als roten Hinweis auf der Startseite und bearbeitet sie im ModCP.',
                'optionscode' => 'onoff',
                'value'       => '1',
                'disporder'   => 9,
            ],
            [
                'name'        => 'jobliste_requests_pm',
                'title'       => 'PN bei Entscheidung',
                'description' => 'Antragsteller:innen per PN informieren, wenn ihr Kategorie-Antrag angenommen oder abgelehnt wurde. Absender ist das bearbeitende Teammitglied.',
                'optionscode' => 'onoff',
                'value'       => '1',
                'disporder'   => 10,
            ],
        ];

        foreach ($settings as $setting) {
            if ($db->fetch_array($db->simple_select('settings', 'sid', "name = '" . $db->escape_string($setting['name']) . "'"))) {
                continue; // idempotent
            }
            $setting = array_map(static function ($value) use ($db) {
                return is_string($value) ? $db->escape_string($value) : $value;
            }, $setting);
            $setting['gid'] = $gid;
            $db->insert_query('settings', $setting);
        }
    }

    // --- Templates ------------------------------------------------------------------

    private static function install_templates(): void
    {
        global $db;

        $template_dir = MYBB_ROOT . "inc/plugins/jobliste/templates/";
        if (!is_dir($template_dir)) {
            return;
        }
        $handle = opendir($template_dir);
        if (!$handle) {
            return;
        }
        while (($file = readdir($handle)) !== false) {
            if (substr($file, -4) !== '.tpl') {
                continue;
            }
            $title = substr($file, 0, -4);
            $content = (string)file_get_contents($template_dir . $file);

            // Idempotent: vorhandenes globales Template (sid=-1) ersetzen statt duplizieren.
            $db->delete_query('templates', "title = '" . $db->escape_string($title) . "' AND sid = -1");
            $db->insert_query('templates', [
                'title'    => $db->escape_string($title),
                'template' => $db->escape_string($content),
                'sid'      => -1,
                'version'  => '',
                'dateline' => TIME_NOW,
            ]);
        }
        closedir($handle);
    }

    // --- Stylesheets ----------------------------------------------------------------

    private static function install_stylesheet(): void
    {
        global $db;

        require_once MYBB_ROOT . "inc/adminfunctions_templates.php";
        require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

        $css_path = MYBB_ROOT . "inc/plugins/jobliste/assets/jobliste.css";
        if (!file_exists($css_path)) {
            return;
        }
        $css = $db->escape_string((string)file_get_contents($css_path));
        $themes = $db->simple_select('themes', 'tid');
        while ($theme = $db->fetch_array($themes)) {
            $tid = (int)$theme['tid'];
            $existing = $db->fetch_array($db->simple_select(
                'themestylesheets',
                'sid',
                "name = '" . self::STYLESHEET . "' AND tid = " . $tid
            ));
            if ($existing) {
                $db->update_query('themestylesheets', [
                    'attachedto'   => self::STYLESHEET_ATTACHED,
                    'stylesheet'   => $css,
                    'lastmodified' => TIME_NOW,
                ], "sid=" . (int)$existing['sid'], 1);
            } else {
                $sid = (int)$db->insert_query('themestylesheets', [
                    'name'         => self::STYLESHEET,
                    'tid'          => $tid,
                    'attachedto'   => self::STYLESHEET_ATTACHED,
                    'stylesheet'   => $css,
                    'lastmodified' => TIME_NOW,
                    'cachefile'    => self::STYLESHEET,
                ]);
                $db->update_query('themestylesheets',
                    ['cachefile' => "css.php?stylesheet={$sid}"], "sid=" . $sid, 1);
            }
            update_theme_stylesheet_list($tid);
        }
    }

    /**
     * jobliste.php rendert die UserCP-Sidebar ({$usercpnav}) selbst; usercp.css ist aber
     * per Default nur an usercp.php gebunden (debugging.md Punkt 20, Fix 5).
     */
    private static function attach_usercp_stylesheet(): void
    {
        global $db;

        require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

        $themes = $db->simple_select('themes', 'tid');
        while ($theme = $db->fetch_array($themes)) {
            $tid = (int)$theme['tid'];
            $stylesheet = $db->fetch_array($db->simple_select(
                'themestylesheets',
                'sid, attachedto',
                "name = 'usercp.css' AND tid = " . $tid
            ));
            if (!$stylesheet) {
                continue;
            }
            $pages = array_filter(explode('|', (string)$stylesheet['attachedto']), 'strlen');
            if (in_array(self::SCRIPT, $pages, true)) {
                continue;
            }
            $pages[] = self::SCRIPT;
            $db->update_query('themestylesheets', [
                'attachedto' => $db->escape_string(implode('|', $pages)),
            ], "sid=" . (int)$stylesheet['sid'], 1);
            update_theme_stylesheet_list($tid);
        }
    }

    private static function detach_usercp_stylesheet(): void
    {
        global $db;

        $themes = $db->simple_select('themes', 'tid');
        while ($theme = $db->fetch_array($themes)) {
            $tid = (int)$theme['tid'];
            $stylesheet = $db->fetch_array($db->simple_select(
                'themestylesheets',
                'sid, attachedto',
                "name = 'usercp.css' AND tid = " . $tid
            ));
            if (!$stylesheet) {
                continue;
            }
            $pages = array_filter(
                explode('|', (string)$stylesheet['attachedto']),
                static function (string $page): bool {
                    return $page !== '' && $page !== self::SCRIPT;
                }
            );
            $db->update_query('themestylesheets', [
                'attachedto' => $db->escape_string(implode('|', $pages)),
            ], "sid=" . (int)$stylesheet['sid'], 1);
        }
    }

    // --- UserCP-Menuepunkt (Box "Sonstiges") --------------------------------------------

    /** Unveraenderte Kern-Zeile "Entwuerfe" in usercp_nav_misc als Anker. */
    private static function usercp_nav_misc_anchor(): string
    {
        return '<tr><td class="trow1 smalltext"><a href="usercp.php?action=drafts" class="usercp_nav_item usercp_nav_drafts">{$draftcount}</a></td></tr>';
    }

    /**
     * Komplette Zeile statisch gebacken, nur {$lang->...} wird zur Laufzeit aufgeloest
     * (debugging.md Punkt 20d). Icon als Inline-SVG (Aktentasche).
     */
    public static function usercp_nav_misc_row(): string
    {
        return '<tr><td class="trow1 smalltext"><a href="jobliste.php" class="usercp_nav_item usercp_nav_jobliste" style="padding-left: 0;"><svg class="usercp_nav_icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" aria-hidden="true" style="vertical-align: -3px; margin-right: 6px;"><rect x="1.5" y="5" width="13" height="9" rx="1.5" stroke-width="1.3"/><path d="M5.5 5V3.5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1V5M1.5 9h13" stroke-width="1.3"/></svg>{$lang->jobliste_usercp_menu_item}</a></td></tr>';
    }

    private static function install_usercp_nav_misc_link(): void
    {
        // h4w: the menu row lives in the RPG category templates (usercp_nav_rpg_*), not in usercp_nav_misc.
        // Only clean up rows baked into usercp_nav_misc by earlier versions.
        self::uninstall_usercp_nav_misc_link();
}

    // --- Startseiten-Hinweis + ModCP-Menue (Kategorie-Antraege) ----------------------------

    private static function install_index_notice(): void
    {
        require_once MYBB_ROOT . "inc/adminfunctions_templates.php";

        // Idempotent: zuerst entfernen, dann neu einhaengen.
        self::uninstall_index_notice();
        find_replace_templatesets(
            'index',
            '#' . preg_quote('{$header}', '#') . '#',
            "{\$header}\n{\$jobliste_request_notice}"
        );
    }

    private static function uninstall_index_notice(): void
    {
        require_once MYBB_ROOT . "inc/adminfunctions_templates.php";

        find_replace_templatesets(
            'index',
            '#\r?\n?' . preg_quote('{$jobliste_request_notice}', '#') . '#',
            '',
            0
        );
    }

    /**
     * {$jobliste_modcp_nav} vor das letzte </table> der ModCP-Navigation. Die Variable
     * wird im Hook modcp_nav gesetzt -- modcp_start laeuft erst NACH dem eval() von
     * modcp_nav und waere zu spaet.
     */
    private static function install_modcp_nav(): void
    {
        require_once MYBB_ROOT . "inc/adminfunctions_templates.php";

        self::uninstall_modcp_nav();
        find_replace_templatesets(
            'modcp_nav',
            '#</table>(?![\s\S]*</table>)#',
            "{\$jobliste_modcp_nav}</table>"
        );
    }

    private static function uninstall_modcp_nav(): void
    {
        require_once MYBB_ROOT . "inc/adminfunctions_templates.php";

        find_replace_templatesets(
            'modcp_nav',
            '#' . preg_quote('{$jobliste_modcp_nav}', '#') . '#',
            '',
            0
        );
    }

    private static function uninstall_usercp_nav_misc_link(): void
    {
        require_once MYBB_ROOT . "inc/adminfunctions_templates.php";

        // Match by href + class instead of the exact row, so rows baked by older
        // versions (e.g. different inline style) are removed as well.
        find_replace_templatesets(
            'usercp_nav_misc',
            '#<tr><td[^>]*><a href="jobliste\.php" class="usercp_nav_item usercp_nav_jobliste"[^>]*>.*?</a></td></tr>#i',
            '',
            0
        );
    }
}
