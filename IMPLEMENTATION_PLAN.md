# Jobliste – Implementation Plan

**Umsetzung:** folge `D:\mybb-plugin-dev-kit\workflows\2-implement.md`. Schritte der Reihe
nach. Bei Unklarheit gezielt nachfragen, aber nicht umplanen. Schritt nach Erledigung auf
`- [x]` setzen.

**Voraussetzung:** Das Schwester-Plugin `relatives`
(`F:\RPG\Mybb Plugins - eigene\relatives`) sollte bereits umgesetzt sein, bevor Schritt 8
(Migration) begonnen wird – siehe dortige `PROJECT_CONTEXT.md`.

---

## Schritt 1 – Grundgerüst & Zugriffsschutz
- [x] **Ziel:** Hauptdatei anlegen, Zugriffsschutz, Hook-Registrierung (Platzhalter-Handler),
      `info()`-Block, Verzeichnisstruktur (`inc/plugins/jobliste/core|assets|templates|
      modules/admin`) anlegen.
- **Dateien:** `inc/plugins/jobliste.php`, `inc/plugins/jobliste/core/*.php` (leere
  Klassen-Gerüste: `Database`, `Categories`, `EntityResolver`, `Installer`, `Migration`)
- **Vorlage:** `D:\mybb-plugin-dev-kit\standards\boilerplate.md` §1, §2
- **Abnahme:** `php -l` sauber; Plugin erscheint in der ACP-Pluginliste.

## Schritt 2 – Lifecycle (install/is_installed/uninstall)
- [x] **Ziel:** Alle 3 Tabellen anlegen/droppen (`jobliste_categories`, `jobliste_jobs`,
      `jobliste_npcs`), `is_installed()`. Keine Seed-Kategorien einfügen.
- **Dateien:** `inc/plugins/jobliste.php`, `inc/plugins/jobliste/core/Installer.php`
- **Vorlage:** `boilerplate.md` §3 · Schema exakt aus `PROJECT_CONTEXT.md` „Datenbank-Tabellen" ·
  `conventions.md` §4
- **Abnahme:** CLI-Lifecycle `install` legt alle 3 Tabellen korrekt an (`testing.md` §3).

## Schritt 3 – activate()/deactivate() (Settings, Templates, Stylesheet)
- [x] **Ziel:** Die 4 Settings aus `PROJECT_CONTEXT.md` idempotent anlegen (`jobliste_
      team_usergroups` als `groupselect`-optionscode, siehe MyBB-Core-Referenz für die exakte
      Syntax), Templates laden, Stylesheet (`jobliste.css`) in alle Themes einhängen (Schleife
      über alle Themes, kein Hardcode auf `tid=1`); `deactivate()` exaktes Spiegelbild.
- **Dateien:** `inc/plugins/jobliste.php`, `inc/plugins/jobliste/templates/*.tpl`,
  `inc/plugins/jobliste/assets/jobliste.css`
- **Vorlage:** `boilerplate.md` §4, §5, §6, §9
- **Abnahme:** CLI `install`→`activate`→`deactivate`→`uninstall` – alle Zähler 0 (`testing.md`
  §3).

## Schritt 4 – Kategoriebaum-Verwaltung (Business-Logik)
- [x] **Ziel:** CRUD für `jobliste_categories` (add/edit/delete/reorder), Zyklen-Schutz beim
      Verschieben (`parent_id` darf nicht auf einen eigenen Nachfahren zeigen), Ableitung
      „hat Kinder?"/„Blattknoten?" per Query statt Magic-Number-Spalte.
- **Dateien:** `inc/plugins/jobliste/core/Categories.php`, `inc/plugins/jobliste/core/Database.php`
- **Vorlage:** `conventions.md` §3 (Naming: `jobliste_db_*`, `jobliste_get_*`), §4 · Security §6
- **Abnahme:** CLI-Selbsttest: Kategorie unter sich selbst verschieben → abgelehnt; normale
  Verschiebung → erlaubt.

## Schritt 5 – Job-Einträge (jobliste_jobs) & Entity-Resolver
- [x] **Ziel:** CRUD für `jobliste_jobs`, Owner-/Team-Berechtigungsprüfung (eigene `entity_type=
      'user'`-Einträge nur durch sich selbst, `entity_type='relative'` nur wenn die Entität über
      `jobliste_collect_entities` für den aktuellen User geliefert wurde, `entity_type='npc'`
      und Moderation fremder Einträge nur für `jobliste_team_usergroups`-Mitglieder). Limit-Check
      gegen `jobliste_max_jobs_per_entity`. `EntityResolver` implementiert `jobliste_sanitize_
      entity()` (Vertragsregeln aus `PROJECT_CONTEXT.md`).
- **Dateien:** `inc/plugins/jobliste/core/Database.php`, `inc/plugins/jobliste/core/EntityResolver.php`
- **Vorlage:** `conventions.md` §3, §4, §6, §7 (Sanitize-Pflicht für Provider-Daten)
- **Abnahme:** CLI-Selbsttest: `entity_type='relative'` mit nicht-eigener Relatives-Person →
  abgelehnt; Team-Account legt NPC-Eintrag an → erlaubt; Nicht-Team-Account → abgelehnt.

## Schritt 6 – Hooks / Frontend / Templates
- [x] **Ziel:**
      - `jobliste_handle_usercp_menu` (Hook `usercp_menu`): eigener Menüpunkt.
      - `jobliste_handle_user_delete` (Hook `datahandler_user_delete_start`): Cleanup wie in
        `PROJECT_CONTEXT.md` beschrieben.
      - Root-Controller `jobliste.php`: Actions `index` (eigene Jobs + zuweisbare Verwandte,
        Entity-Auswahl aus `jobliste_collect_entities`), `add`/`edit`/`delete` (POST +
        `verify_post_check`, `my_post_key`, **kein** GET-Delete-Link mehr – fixt Alt-CSRF-Bug),
        `overview` (Gesamtübersicht, ersetzt `listen_jobs.php`: einklappbarer `<details>`-
        Kategoriebaum + clientseitiger Suchfilter, vanilla JS, kein `height:1200px`-Hardcode
        mehr), `npc-manage` (nur Team).
      - Beruf-Auswahl im Add/Edit-Formular: durchsuchbares Kombo-Feld über den Kategoriebaum
        (Datalist/eigene JS-Suche, kein CDN-jQuery, keine mehrstufigen Cascading-Selects mit dem
        alten `type=undefined`-Bug).
- **Dateien:** `jobliste.php` (Root), `inc/plugins/jobliste.php` (Hook-Handler),
  `inc/plugins/jobliste/templates/*.tpl`, `inc/plugins/jobliste/assets/jobliste.js`
- **Vorlage:** `boilerplate.md` §11 · Hook-Referenz `conventions.md` §5
- **Abnahme:** curl-Verifikation (`testing.md` §5) für alle Actions; manueller Klicktest der
  Kombo-Suche und der einklappbaren Übersicht an den Nutzer übergeben.

## Schritt 7 – ACP-Modul
- [x] **Ziel:** Tools-Modul mit Tabs „Kategoriebaum" (add/edit/delete/reorder,
      Drag&Drop-Sortierung optional, mindestens Auf/Ab-Buttons), „NPCs verwalten", „Job-
      Moderation" (alle Einträge durchsuchen/filtern/löschen, unabhängig von
      `jobliste_team_usergroups`-ACP-Rechten – ACP-Zugriff ist ohnehin Admin-only), „Migration".
- **Dateien:** `admin/modules/tools/jobliste.php`, `inc/plugins/jobliste/modules/admin/*.php`,
  `inc/plugins/jobliste/core/Migration.php`
- **Vorlage:** `boilerplate.md` §12 · ACP-Nav-Tabs-Muster aus globaler `CLAUDE.md`
  „ACP Page Structure" (Root-Kontext) – Pflicht `output_nav_tabs()`, jede Tab-`description`
  nicht-leer.
- **Abnahme:** `php -l`; Methoden-Abgleich gegen Referenzmodul; **manueller Klicktest an Nutzer
  übergeben** (`testing.md` §8).

## Schritt 7b – Kategorietyp „Ebenen“ (Erweiterung, in Phase 2 mit der Nutzerin abgestimmt)
- [x] **Ziel:** Kategorien bekommen einen Typ (`tree` = Baumstruktur, `levels` = Ebenen, siehe
      `PROJECT_CONTEXT.md` „Kategorietypen“). Neue Tabelle `jobliste_category_levels`, neue
      Spalten `jobliste_categories.type` und `jobliste_jobs.level_values` (install() +
      idempotentes Nachrüsten in activate()). ACP: Typ im Kategorie-Formular, eigene Seite
      „Ebenen bearbeiten“ (Ebene anlegen/bearbeiten/löschen/sortieren, Quelle Profilfeld oder
      ACP-Liste). Frontend: pro Ebene ein Auswahlfeld (JS blendet nur die Ebenen der gewählten
      Kategorie ein), serverseitige Validierung gegen Profilfeld-/Listenoptionen (beim
      Bearbeiten bleiben bisherige Werte gültig). Gesamtübersicht gruppiert `levels`-Kategorien
      dynamisch nach Ebenenwerten.
- **Dateien:** `core/Installer.php`, `core/Levels.php` (neu), `core/Categories.php`,
  `core/Database.php`, `jobliste.php`, `templates/jobliste_form.tpl`, `assets/jobliste.js`,
  `modules/admin/categories.php`, Sprachdateien
- **Abnahme:** CLI-Selbsttest (Typwechsel-Regeln, Optionsvalidierung, Profilfeld-Parsing),
  Harness-Render von Formular/Übersicht, Lifecycle weiterhin sauber.

## Schritt 8 – Migration (setzt migriertes Relatives-Plugin voraus)
- [x] **Ziel:** Migrations-Tool implementieren wie in `PROJECT_CONTEXT.md` „Migration"
      beschrieben (3 Schritte: Kategorien, `mybb_jobs`, `beruf`-Spalten). Vorab-Check per
      `table_exists()` auf `relatives_persons` – klare Fehlermeldung + Abbruch, falls Relatives
      noch nicht migriert ist. Re-Run-Schutz über `jobliste_legacy_migrated`.
- **Dateien:** `inc/plugins/jobliste/core/Migration.php`
- **Vorlage:** Pfad-Parsing-Logik 1:1 wie im dokumentierten Altcode-Verhalten (siehe
  `PROJECT_CONTEXT.md`), aber mit `TABLE_PREFIX` statt hartcodiertem `mybb_` (Altbug #1) und
  `escape_string()`/`(int)` auf allen Legacy-Werten.
- **Abnahme:** Migrations-Lauf gegen eine Kopie der Live-Legacy-Tabellen auf dem Testforum;
  Abschlussbericht zeigt plausible Zahlen; erneuter Lauf ohne „force" → No-Op mit Warnhinweis.

## Schritt 9 – Provider-Hook-Konsum aus Relatives testen
- [x] **Ziel:** Verifizieren, dass `jobliste_collect_entities`/`jobliste_resolve_entity_label`
      korrekt funktionieren **sowohl** mit aktivem Relatives-Plugin (Verwandte erscheinen in
      Entity-Auswahl und Übersicht) **als auch ohne** (kein Fehler, sinnvoller Fallback-Text wie
      „Verwandte (Plugin inaktiv)" statt kaputtem Label). `jobliste_relatives_integration=0`
      testen (Hooks werden trotz aktivem Relatives-Plugin ignoriert).
- **Dateien:** keine neuen (Testschritt gegen Schritt 5/6)
- **Vorlage:** `conventions.md` §7 „Testbar mit einem Wegwerf-Provider-Plugin", `testing.md` §7
- **Abnahme:** Alle drei Zustände (Relatives aktiv+Integration an, Relatives aktiv+Integration
  aus, Relatives inaktiv) manuell/curl durchgespielt, kein Fehler/Warning in keinem Fall.

## Schritt 10 – Sprachdateien (englisch + deutsch_du, Frontend + Admin getrennt)
- [x] **Ziel:** Alle genutzten Keys definieren. UI-Texte genderneutral formulieren
      (Nutzer:innen, „du"-Ansprache).
- **Dateien:** `inc/languages/english/jobliste.lang.php` (+ `admin/jobliste.lang.php`),
  `inc/languages/deutsch_du/jobliste.lang.php` (+ `admin/jobliste.lang.php`)
- **Vorlage:** `boilerplate.md` §13
- **Abnahme:** `D:\mybb-plugin-dev-kit\scripts\lang_check.ps1` ohne fehlende Keys.

## Schritt 11 – Selbsttests finalisieren
- [x] **Ziel:** `tests/cli-plugin.php` (Lifecycle) + `tests/cli-selftest.php` (Kategoriebaum-
      Zyklenschutz, Entity-Berechtigungsprüfung, Migrations-Pfad-Parsing gegen die echten
      Beispieldaten aus `F:\RPG\Mybb Plugins - eigene\Alt und zu überarbeiten\Verwandte\php5\
      SQL\mybb_relatives_items.sql`, Provider-Hook-Fallback-Verhalten).
- **Vorlage:** `testing.md` §3, §4
- **Abnahme:** Lifecycle grün, Selbsttest 0 failed.

## Schritt 12 – Beruf im Personenformular von Relatives (Erweiterung nach Phase 2)
- [x] **Ziel:** Listener fuer die Relatives-Hooks `relatives_person_form_fields`,
      `relatives_person_validate`, `relatives_person_saved` (Regeln siehe `PROJECT_CONTEXT.md`
      „Beruf im Personenformular von Relatives“). Gemeinsame Formular-Bausteine nach
      `core/FormFields.php` (Praefix-Parameter), Templates `jobliste_relatives_*`,
      `jobliste.css` zusaetzlich an `relatives.php`, `jobliste.js` mit `[data-jobliste-scope]`.
- **Dateien:** `inc/plugins/jobliste.php`, `core/FormFields.php` (neu), `core/Installer.php`,
  `jobliste.php`, `assets/jobliste.js`, `assets/jobliste.css`, 3 Templates, Sprachdateien,
  `tests/cli-selftest.php`
- **Abnahme (offen):** Plugin re-aktivieren, Selbsttest 0 failed; manuell im Testforum:
  Person anlegen/bearbeiten mit Beruf (Baum-, Ebenen-, Freitext-Kategorie), „Kein Beruf“
  entfernt, Schnellformular im Stammbaum laesst den Beruf unveraendert, fremder gespielter
  Charakter nur lesend.

---

**Hinweis für Schwester-Plugin:** Sobald dieses Plugin umgesetzt und migriert ist, kann
`anschluss suchmaschine` unabhängig davon entwickelt werden (hat keine Abhängigkeit zu
Jobliste, nur zu Relatives).
