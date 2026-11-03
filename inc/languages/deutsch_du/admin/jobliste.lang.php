<?php
/**
 * Jobliste - Admin-Sprachdatei (deutsch_du)
 */

$l['jobliste'] = "Jobliste";
$l['jobliste_acp_title'] = "Jobliste";

// Settinggroup
$l['setting_group_jobliste'] = "Jobliste";
$l['setting_group_jobliste_desc'] = "Einstellungen für die Jobliste (Berufe von Charakteren, Verwandten und NPCs).";

// Tabs
$l['jobliste_tab_categories'] = "Kategoriebaum";
$l['jobliste_tab_desc_categories'] = "Arbeitsorte und Branchen anlegen, verschachteln, sortieren und festlegen, wo Freitext erlaubt ist.";
$l['jobliste_tab_npcs'] = "NPCs verwalten";
$l['jobliste_tab_desc_npcs'] = "NPCs ohne Account anlegen, bearbeiten und löschen und ihre Berufe pflegen. Berufe kann das Team auch im Frontend unter Jobliste → NPCs verwalten eintragen.";
$l['jobliste_tab_moderation'] = "Job-Moderation";
$l['jobliste_tab_desc_moderation'] = "Alle eingetragenen Berufe durchsuchen, filtern und bei Bedarf löschen.";
$l['jobliste_tab_migration'] = "Migration";
$l['jobliste_tab_desc_migration'] = "Einmaliger Import aus dem alten Berufe-Plugin (relatives_items, jobs, relatives.beruf, wanted.beruf).";

// Allgemein
$l['jobliste_acp_actions_title'] = "Aktionen";
$l['jobliste_acp_edit'] = "Bearbeiten";
$l['jobliste_acp_delete'] = "Löschen";
$l['jobliste_acp_inactive'] = "inaktiv";
$l['jobliste_acp_not_found'] = "Der Eintrag wurde nicht gefunden.";
$l['jobliste_acp_error_generic'] = "Die Aktion konnte nicht ausgeführt werden.";
$l['jobliste_acp_no_category'] = "Ohne Kategorie";
$l['jobliste_acp_no_jobs'] = "Keine Berufe";
$l['jobliste_acp_legacy'] = "Altdaten";
$l['jobliste_acp_available'] = "vorhanden";
$l['jobliste_acp_missing'] = "nicht vorhanden";

// Entitaetstypen + Fallback-Labels (auch im ACP benoetigt)
$l['jobliste_type_user'] = "Charakter";
$l['jobliste_type_relative'] = "Verwandte:r";
$l['jobliste_type_npc'] = "NPC";
$l['jobliste_fallback_user'] = "Gelöschter Account";
$l['jobliste_fallback_npc'] = "Gelöschter NPC";
$l['jobliste_fallback_relative'] = "Verwandte*r (nicht gefunden)";
$l['jobliste_fallback_unknown'] = "Unbekannt";

// Kategoriebaum
$l['jobliste_acp_categories_overview'] = "Kategoriebaum";
$l['jobliste_acp_add_category'] = "Kategorie anlegen";
$l['jobliste_acp_add_category_desc'] = "Neue Kategorie auf oberster Ebene anlegen. Unterkategorien legst du direkt über „Unterkategorie“ in der jeweiligen Zeile an.";
$l['jobliste_acp_edit_category'] = "Kategorie bearbeiten";
$l['jobliste_acp_add_child'] = "Unterkategorie";
$l['jobliste_acp_edit_levels'] = "Ebenen bearbeiten";
$l['jobliste_acp_no_categories'] = "Es wurden noch keine Kategorien angelegt.";
$l['jobliste_acp_col_name'] = "Name";
$l['jobliste_acp_col_type'] = "Typ";
$l['jobliste_acp_col_custom_text'] = "Freitext";
$l['jobliste_acp_col_active'] = "Aktiv";
$l['jobliste_acp_col_jobs'] = "Berufe";
$l['jobliste_acp_col_order'] = "Reihenfolge";
$l['jobliste_acp_col_actions'] = "Aktionen";
$l['jobliste_acp_field_name'] = "Name";
$l['jobliste_acp_field_parent'] = "Übergeordnete Kategorie";
$l['jobliste_acp_field_parent_desc'] = "Wo soll die Kategorie im Baum hängen? Kategorien vom Typ „Ebenen“ können keine Unterkategorien haben.";
$l['jobliste_acp_parent_root'] = "(oberste Ebene)";
$l['jobliste_acp_field_type'] = "Typ";
$l['jobliste_acp_field_type_desc'] = "<strong>Baumstruktur:</strong> alle Unterkategorien legst du hier per Hand an.<br /><strong>Ebenen:</strong> keine Unterkategorien; stattdessen definierst du Ebenen (z. B. Land, Sektor), deren Auswahlmöglichkeiten aus einem Profilfeld oder einer Liste kommen.";
$l['jobliste_acp_type_tree'] = "Baumstruktur";
$l['jobliste_acp_type_levels'] = "Ebenen";
$l['jobliste_acp_field_allow_custom_text'] = "Freitext erlaubt?";
$l['jobliste_acp_field_allow_custom_text_desc'] = "Dürfen Mitglieder bei dieser Kategorie zusätzlich eine genaue Tätigkeit als Freitext angeben (z. B. „Lehrkraft für Zaubertränke“)?";
$l['jobliste_acp_field_is_active'] = "Aktiv?";
$l['jobliste_acp_field_is_active_desc'] = "Inaktive Kategorien (samt allem darunter) können nicht mehr neu ausgewählt werden. Bestehende Einträge bleiben erhalten und werden weiter angezeigt.";
$l['jobliste_acp_category_saved'] = "Die Kategorie wurde gespeichert.";
$l['jobliste_acp_category_saved_levels'] = "Die Kategorie wurde gespeichert. Lege jetzt ihre Ebenen fest.";
$l['jobliste_acp_category_deleted'] = "Die Kategorie wurde gelöscht.";
$l['jobliste_acp_category_deleted_moved'] = "Die Kategorie wurde gelöscht. {1} Berufe wurden in die übergeordnete Kategorie verschoben.";
$l['jobliste_acp_confirm_delete_category'] = "Diese Kategorie wirklich löschen? Berufe, die ihr zugeordnet sind, wandern in die übergeordnete Kategorie.";
$l['jobliste_acp_error_name'] = "Bitte gib einen Namen ein.";
$l['jobliste_acp_error_parent'] = "Die übergeordnete Kategorie existiert nicht.";
$l['jobliste_acp_error_cycle'] = "Eine Kategorie kann nicht unter sich selbst oder unter eine ihrer eigenen Unterkategorien verschoben werden.";
$l['jobliste_acp_error_has_children'] = "Diese Kategorie hat noch Unterkategorien. Lösche oder verschiebe zuerst die Unterkategorien.";
$l['jobliste_acp_error_levels_has_children'] = "Eine Kategorie mit Unterkategorien kann nicht auf den Typ „Ebenen“ umgestellt werden. Verschiebe oder lösche zuerst die Unterkategorien.";
$l['jobliste_acp_error_parent_levels'] = "Unter einer Kategorie vom Typ „Ebenen“ können keine Unterkategorien angelegt werden.";

// Ebenen
$l['jobliste_acp_levels_title'] = "Ebenen von „{1}“";
$l['jobliste_acp_levels_none'] = "noch keine Ebenen festgelegt";
$l['jobliste_acp_levels_not_levels_type'] = "Diese Kategorie hat den Typ „Baumstruktur“. Die Ebenen werden erst verwendet, wenn du den Typ auf „Ebenen“ umstellst.";
$l['jobliste_acp_add_level'] = "Ebene hinzufügen";
$l['jobliste_acp_edit_level'] = "Ebene bearbeiten";
$l['jobliste_acp_col_level'] = "Ebene";
$l['jobliste_acp_col_source'] = "Quelle";
$l['jobliste_acp_col_options'] = "Auswahlmöglichkeiten";
$l['jobliste_acp_field_level_label'] = "Bezeichnung";
$l['jobliste_acp_field_level_label_desc'] = "Wird im Formular über dem Auswahlfeld angezeigt, z. B. „Land“ oder „Sektor“.";
$l['jobliste_acp_field_source'] = "Quelle der Auswahlmöglichkeiten";
$l['jobliste_acp_field_source_desc'] = "<strong>Profilfeld:</strong> die Optionen eines Auswahl-Profilfelds (z. B. Herkunftsland) – Änderungen am Profilfeld wirken sofort.<br /><strong>Liste:</strong> die Werte, die du unten einträgst.";
$l['jobliste_acp_source_profilefield'] = "Profilfeld";
$l['jobliste_acp_source_list'] = "Liste";
$l['jobliste_acp_field_profilefield'] = "Profilfeld";
$l['jobliste_acp_field_profilefield_desc'] = "Nur bei Quelle „Profilfeld“. Es stehen nur Profilfelder mit festen Optionen (Auswahlliste, Radio-Buttons, Mehrfachauswahl, Checkboxen) zur Wahl.";
$l['jobliste_acp_profilefield_choose'] = "– Profilfeld wählen –";
$l['jobliste_acp_profilefield_missing'] = "Profilfeld existiert nicht mehr";
$l['jobliste_acp_field_options'] = "Liste";
$l['jobliste_acp_field_options_desc'] = "Nur bei Quelle „Liste“: ein Eintrag pro Zeile, z. B. die Sektoren.";
$l['jobliste_acp_level_no_options'] = "keine";
$l['jobliste_acp_level_saved'] = "Die Ebene wurde gespeichert.";
$l['jobliste_acp_level_deleted'] = "Die Ebene wurde gelöscht.";
$l['jobliste_acp_confirm_delete_level'] = "Diese Ebene wirklich löschen? Bereits gespeicherte Werte an den Berufen bleiben erhalten.";
$l['jobliste_acp_error_level'] = "Die Ebene konnte nicht gespeichert werden. Bitte gib eine Bezeichnung an und wähle bei Quelle „Profilfeld“ ein gültiges Profilfeld aus.";

// NPCs
$l['jobliste_acp_npcs_overview'] = "NPCs";
$l['jobliste_acp_add_npc'] = "NPC anlegen";
$l['jobliste_acp_add_npc_desc'] = "Neuen NPC anlegen – den Beruf kannst du direkt im Formular auswählen. Weitere Berufe ergänzt du über „Bearbeiten“; das Team kann sie auch im Frontend unter Jobliste → NPCs verwalten eintragen.";
$l['jobliste_acp_edit_npc'] = "NPC bearbeiten";
$l['jobliste_acp_no_npcs'] = "Es wurden noch keine NPCs angelegt.";
$l['jobliste_acp_field_npc_name'] = "Name";
$l['jobliste_acp_field_npc_notes'] = "Notizen";
$l['jobliste_acp_field_npc_notes_desc'] = "Optional, nur für das Team sichtbar.";
$l['jobliste_acp_col_created_by'] = "Angelegt von";
$l['jobliste_acp_npc_saved'] = "Der NPC wurde gespeichert.";
$l['jobliste_acp_npc_deleted'] = "Der NPC wurde samt seiner Berufe gelöscht.";
$l['jobliste_acp_confirm_delete_npc'] = "Diesen NPC samt aller seiner Berufe wirklich löschen?";
$l['jobliste_acp_error_npc_name'] = "Bitte gib einen Namen für den NPC ein.";
$l['jobliste_acp_no_job_option'] = "– kein Beruf –";
$l['jobliste_acp_field_job'] = "Beruf";
$l['jobliste_acp_field_job_n'] = "Beruf {1}";
$l['jobliste_acp_field_job_new'] = "Weiteren Beruf hinzufügen";
$l['jobliste_acp_field_job_desc'] = "Optional. Tippe ins Suchfeld, um die Liste zu filtern, und wähle dann einen Eintrag aus.";
$l['jobliste_acp_field_job_existing_desc'] = "Wähle „– kein Beruf –“, um diesen Beruf beim Speichern zu entfernen.";

// Moderation
$l['jobliste_acp_filter_title'] = "Filter";
$l['jobliste_acp_filter_type'] = "Für wen";
$l['jobliste_acp_filter_category'] = "Kategorie";
$l['jobliste_acp_filter_search'] = "Suche";
$l['jobliste_acp_filter_search_desc'] = "Sucht in der genauen Tätigkeit (Freitext) und in den Ebenenwerten.";
$l['jobliste_acp_filter_submit'] = "Filtern";
$l['jobliste_acp_filter_all'] = "Alle";
$l['jobliste_acp_jobs_overview'] = "Berufe ({1})";
$l['jobliste_acp_no_jobs_found'] = "Keine Berufe gefunden.";
$l['jobliste_acp_col_id'] = "ID";
$l['jobliste_acp_col_entity'] = "Person";
$l['jobliste_acp_col_job'] = "Beruf";
$l['jobliste_acp_col_updated'] = "Zuletzt geändert";
$l['jobliste_acp_job_deleted'] = "Der Beruf wurde gelöscht.";
$l['jobliste_acp_confirm_delete_job'] = "Diesen Beruf wirklich löschen?";

// Migration
$l['jobliste_acp_migration_status'] = "Status der Altdaten";
$l['jobliste_acp_migration_last_run'] = "Letzte Migration";
$l['jobliste_acp_migration_never'] = "noch nie";
$l['jobliste_acp_migration_run_title'] = "Migration starten";
$l['jobliste_acp_migration_run'] = "Migration starten";
$l['jobliste_acp_migration_profilefield'] = "Profilfeld für die Ebene „Land“";
$l['jobliste_acp_migration_profilefield_desc'] = "Kategorien im alten Sonderformat (z. B. „Muggel“, „nicht GB“ mit Land und Sektor als Freitext) werden auf den Typ „Ebenen“ umgestellt. Die Ebene „Land“ bekommt ihre Auswahl aus diesem Profilfeld, die Ebene „Sektor“ eine Liste aus den Altdaten. Ohne Profilfeld wird auch „Land“ eine Liste aus den Altdaten.";
$l['jobliste_acp_migration_no_profilefield'] = "– kein Profilfeld (Liste aus den Altdaten) –";
$l['jobliste_acp_migration_force'] = "Erneut ausführen (force)?";
$l['jobliste_acp_migration_force_desc'] = "Die Migration wurde bereits ausgeführt. Ein erneuter Lauf ergänzt nur fehlende Einträge und erzeugt keine Duplikate.";
$l['jobliste_acp_migration_already_done'] = "Die Migration wurde bereits ausgeführt. Setze „Erneut ausführen (force)“ auf Ja, um fehlende Einträge zu ergänzen.";
$l['jobliste_acp_migration_no_items'] = "Die alte Tabelle relatives_items fehlt – es gibt nichts zu migrieren.";
$l['jobliste_acp_migration_no_relatives'] = "Das Relatives-Plugin ist noch nicht installiert bzw. migriert (Tabelle relatives_persons fehlt). Installiere und migriere zuerst das Relatives-Plugin, sonst können die Berufe der Verwandten nicht zugeordnet werden.";
$l['jobliste_acp_migration_level_country'] = "Land";
$l['jobliste_acp_migration_level_sector'] = "Sektor";
$l['jobliste_acp_report_title'] = "Ergebnis der Migration";
$l['jobliste_acp_report_categories_created'] = "Kategorien importiert";
$l['jobliste_acp_report_categories_existing'] = "Kategorien bereits vorhanden";
$l['jobliste_acp_report_level_categories'] = "Auf Typ „Ebenen“ umgestellt";
$l['jobliste_acp_report_jobs_user'] = "Berufe von Charakteren importiert";
$l['jobliste_acp_report_jobs_relative'] = "Berufe von Verwandten importiert";
$l['jobliste_acp_report_jobs_existing'] = "Berufe bereits vorhanden (übersprungen)";
$l['jobliste_acp_report_skipped_empty'] = "Leere Einträge / „Keine Angabe“ (übersprungen)";
$l['jobliste_acp_report_skipped_orphan_users'] = "Berufe gelöschter Accounts (übersprungen)";
$l['jobliste_acp_report_unresolved_relatives'] = "Verwandte ohne Zuordnung in relatives_persons (bitte manuell prüfen)";
$l['jobliste_acp_report_unresolved_items'] = "Unbekannte alte Kategorie-IDs (bitte manuell prüfen)";

// Orphaned relative jobs (moderation)
$l['jobliste_acp_orphans_notice'] = "{1} Beruf(e) gehören zu Verwandten, die das Relatives-Plugin nicht mehr kennt (Person dort gelöscht). Sie werden in der Gesamtliste nicht mehr angezeigt.";
$l['jobliste_acp_orphans_cleanup'] = "Verwaiste Berufe löschen";
$l['jobliste_acp_orphans_confirm'] = "Alle Berufe von nicht mehr vorhandenen Verwandten löschen? Das lässt sich nicht rückgängig machen.";
$l['jobliste_acp_orphans_deleted'] = "{1} verwaiste(r) Beruf(e) gelöscht.";
