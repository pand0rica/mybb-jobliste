# Jobliste – Project Context

## Identität
- **Name:** Jobliste
- **Codename / Dateiname:** `jobliste` (Präfix: `jobliste`)
- **Version:** 2.0.0 (Rework von Altplugin „h4w-multiple-berufe" / „jobs" v1.x)
- **Autor:** pand0rica
- **Kurzbeschreibung:** Verwaltet einen ACP-pflegbaren Kategoriebaum (Arbeitsorte/Branchen) und
  lässt Nutzer:innen Berufe für sich selbst und – optional, wenn das Relatives-Plugin installiert
  ist – für ihre Verwandten eintragen. Team-Mitglieder können zusätzlich Berufe für NPCs
  anlegen. Ersetzt die alte, unübersichtliche verschachtelte Gesamttabellen-Ansicht durch eine
  einklappbare, durchsuchbare Kategorie-Übersicht.
- **MyBB:** 1.8.x · **PHP:** 7.4 + 8.x

## Zweck & Zielgruppe
RPG-Charakterforum (h4w-rpg.de): Übersicht, wer welchen Beruf/Arbeitsplatz im Rollenspiel-Setting
hat – für eigene Charaktere, für Verwandte (optional, siehe Abhängigkeiten) und für NPCs
(Team-Verwaltung). Neu gegenüber dem Altplugin: eigener, ACP-verwalteter Kategoriebaum (statt
der alten Linked-List-Tabelle, die technisch dem Verwandtschafts-Plugin gehörte), vereinfachtes
Hinzufügen (durchsuchbares Kombo-Feld statt mehrstufiger, teils kaputter Cascading-Selects),
klar strukturierte, einklappbare Gesamtübersicht statt einer starren, fix-hohen verschachtelten
Tabelle.

## Architektur
- **Variante:** Klassen-/Core-Split (Begründung: eigener Kategoriebaum mit ACP-CRUD, mehrere
  Entitätstypen (user/relative/npc), Provider-Hook-Konsum, Migrations-Tool mit Cross-Plugin-
  Abhängigkeit – vergleichbar mit RPG Maps/Blacklist, siehe Dev-Kit `conventions.md` §2).
- **Dateilayout** (kanonisches Layout siehe Dev-Kit `conventions.md` §1):
  ```
  jobliste.php                                     # Frontend-Root (MyBB-Wurzelverzeichnis)
  inc/plugins/jobliste.php                         # Lifecycle + Hook-Registrierung
  inc/plugins/jobliste/
      core/
          Database.php        # Datenzugriff jobliste_jobs/_npcs (Repository)
          Categories.php       # CRUD für jobliste_categories (Baum)
          Levels.php           # Ebenen-Definitionen für Kategorien vom Typ "levels"
          FormFields.php       # Kategorie-/Ebenenfelder + Validierung (Praefix-faehig, auch
                                # fuers Personenformular von Relatives)
          EntityResolver.php   # Sammelt/löst Entitäten über jobliste_collect_entities /
                                # jobliste_resolve_entity_label auf (Konsumentenseite)
          Installer.php        # install/uninstall/activate/deactivate-Logik
          Migration.php        # Legacy-Import (relatives_items, mybb_jobs, beruf-Spalten)
      assets/jobliste.css, jobliste.js
      templates/*.tpl
      modules/admin/           # ACP-Tab-Handler (categories, npcs, moderation, migration)
  admin/modules/tools/jobliste.php                 # ACP-Einstiegspunkt
  inc/languages/english/jobliste.lang.php (+ admin/)
  inc/languages/deutsch_du/jobliste.lang.php (+ admin/)
  ```

## Datenbank-Tabellen

| Tabelle (`TABLE_PREFIX .`) | Zweck | Wichtige Spalten |
|---|---|---|
| `jobliste_categories` | ACP-verwalteter Kategoriebaum (Orte/Branchen) | `id, parent_id (int, 0 = Wurzel), name (varchar), allow_custom_text (tinyint(1) – ersetzt die alte `next=2`-Magic-Number: an diesem Knoten darf zusätzlich Freitext eingegeben werden), type (ENUM('tree','levels') default 'tree' – siehe „Kategorietypen“), sort_order (int), is_active (tinyint(1) default 1), legacy_item_id (int, nullable – Mapping auf alte `relatives_items.id` für Migrations-Nachvollziehbarkeit)` |
| `jobliste_jobs` | Ein Berufseintrag | `id, entity_type (ENUM('user','relative','npc')), entity_id (int – uid \| relatives_persons.id \| jobliste_npcs.id, je nach entity_type), category_id (int, FK jobliste_categories, nullable), custom_label (varchar, nullable), level_values (text, nullable – JSON-Array der gewählten Ebenenwerte bei Kategorien vom Typ `levels`, z.B. `["GB","Anderes"]`), created_by_uid (int – wer den Eintrag angelegt hat, bei Team-Einträgen ≠ entity_id), legacy_job_id (int, nullable), created_at (int), updated_at (int)` |
| `jobliste_npcs` | Reine NPC-Einträge ohne Account-/Verwandten-Bezug | `id, name (varchar), notes (text, nullable), created_by_uid (int), created_at (int)` |
| `jobliste_requests` | Kategorie-Antraege aus dem UserCP (Erweiterung nach Phase 2) | `id, uid (Antragsteller:in), parent_id (int, 0 = neue Hauptkategorie), name, note (text – Begruendung), status (ENUM('pending','approved','rejected')), category_id (int – beim Annehmen angelegte Kategorie), response (text – Anmerkung des Teams), handled_by_uid, handled_at, created_at` |
| `jobliste_category_levels` | Ebenen-Definition für Kategorien vom Typ `levels` (Erweiterung aus Phase 2, mit der Nutzerin abgestimmt) | `id, category_id (int), sort_order (int), label (varchar – z.B. „Land“, „Sektor“), source (ENUM('profilefield','list')), profilefield_fid (int – bei source=profilefield: Optionen eines Auswahl-Profilfelds select/radio/multiselect/checkbox), options (text – bei source=list: im ACP gepflegte Werte, eine pro Zeile)` |

### Kategorietypen (Erweiterung aus Phase 2)
Jede Kategorie hat einen Typ, im ACP einstellbar:
- **Baumstruktur (`tree`, Standard):** alle Unterkategorien werden im ACP per Hand angelegt.
- **Ebenen (`levels`):** keine manuellen Unterkategorien; stattdessen eine geordnete Liste von
  Ebenen (`jobliste_category_levels`). Pro Ebene wird eingestellt, woher die Auswahlmöglichkeiten
  kommen: aus einem Auswahl-Profilfeld (z.B. „Herkunftsland“) oder aus einer im ACP gepflegten
  Liste (z.B. Sektoren). Beim Eintragen wählt man pro Ebene einen Wert; gespeichert wird er in
  `jobliste_jobs.level_values`. Freitext am Ende weiterhin nur über `allow_custom_text`.
  Anzeige: „Muggel › GB › Anderes – Militärarzt“. Die Gesamtübersicht gruppiert solche
  Kategorien dynamisch nach den vorkommenden Werten (je Wert ein einklappbarer Unterknoten).
- Eine `levels`-Kategorie darf keine Unterkategorien haben (und umgekehrt). Gespeicherte Werte,
  die später aus Profilfeld/Liste verschwinden, bleiben am Eintrag erhalten.

**Bewusst kein direkter Zugriff auf `relatives_*`-Tabellen** – Kategoriebaum ist jetzt Eigentum
dieses Plugins (Entscheidung aus der Planungsphase: „Jobliste besitzt den Beruf-Kategoriebaum"),
Verwandten-Bezug ausschließlich über die unten dokumentierten Provider-Hooks.

## Settings (settinggroup `jobliste`)

| Setting | optionscode | Zweck |
|---|---|---|
| `jobliste_enabled` | onoff (default 1) | Master-Schalter |
| `jobliste_team_usergroups` | group-Mehrfachauswahl (`groupselect`, kommagetrennte gid-Liste) | Wer gilt als „Team" – darf NPC-Einträge anlegen und fremde Einträge moderieren (löschen/bearbeiten) |
| `jobliste_relatives_integration` | onoff (default 1) | Verwandten-Integration admin-seitig abschaltbar, auch wenn Relatives-Plugin aktiv ist |
| `jobliste_max_jobs_per_entity` | text/numeric (default 0) | Missbrauchsschutz, 0 = unbegrenzt |
| `jobliste_overview_guests` | yesno (default 1) | Gesamtliste (`action=overview`) auch für Gäst*innen; „Forum ansehen" gilt zusätzlich |
| `jobliste_overview_layout_template` | text (default leer) | Optionales Rahmen-Template für die Gesamtliste (z. B. `listen`) |
| `jobliste_overview_nav_template` | text (default leer) | Optionales Navigations-Template (z. B. `listen_nav`), nur zusammen mit dem Rahmen-Template |
| `jobliste_requests_enabled` | onoff (default 1) | Kategorie-Antraege im UserCP an/aus |
| `jobliste_requests_pm` | onoff (default 1) | PN an Antragsteller:in bei Annahme/Ablehnung (Absender = bearbeitendes Teammitglied) |

### Kategorie-Antraege (Erweiterung nach Phase 2)
- **UserCP** Abschnitt `#jobliste_request` auf `jobliste.php` (fuer alle; `?action=request`
  leitet dorthin um): Name,
  Einordnung (Elternkategorie oder neue Hauptkategorie; keine `levels`-Kategorien, keine
  inaktiven Zweige), Begruendung. Liste der eigenen Antraege mit Status/Team-Anmerkung, offene
  zurueckziehbar. Schutz: max. 5 offene Antraege je Person, kein Duplikat einer bestehenden
  Kategorie oder eines offenen Antrags an gleicher Stelle (Gross-/Kleinschreibung egal).
- **Startseite:** roter Balken (`red_alert`, `{$jobliste_request_notice}` hinter `{$header}`
  im Template `index`) fuer Team-Mitglieder mit ModCP-Zugang, solange Antraege offen sind.
- **ModCP** `modcp.php?action=jobliste_requests` (Menuepunkt ueber `{$jobliste_modcp_nav}` in
  `modcp_nav`): offene + zuletzt bearbeitete Antraege; Pruefen = annehmen (Name/Einordnung/
  Freitext anpassbar, legt die Kategorie aktiv an) oder mit Begruendung ablehnen.
  Zugriff: `canmodcp` UND Team-Gruppe (`jobliste_can_handle_requests()`).
- PN wie `private.php` (PMDataHandler + `private_do_send_end`), nicht `send_pm()`.

### Gesamtliste: Darstellung
- Je Wurzelkategorie (GB / nicht GB / Muggel …) ein Tab; ohne JS stehen die Bereiche
  untereinander mit Überschrift. Der Suchfilter durchsucht alle Tabs, Tabs ohne Treffer werden
  abgeblendet. Gewählter Tab steht im URL-Hash (`#jobliste-<id>`).
- Accounts: Profil-Link in Gruppenfarbe (`format_name()`), kein Typ-Badge. NPCs: Badge „NPC“.
  Verwandte: Info-Icon, Popup (Hover/Fokus/Klick) listet die eintragenden Charaktere
  (Gruppenfarbe) mit Verwandtschaftsgrad aus `relations` des Label-Hooks.

### Seitenaufteilung
- **UserCP** (`jobliste.php`, mit UserCP-Sidebar): eine Seite ohne Tabs, von oben nach unten:
  Formular „Beruf hinzufügen“, „Kategorie beantragen“ + eigene Anträge (wenn
  `jobliste_requests_enabled`; Antragsliste nur, wenn vorhanden), Berufe des eigenen Charakters
  und der Verwandten mit Link zur Gesamtliste. Für Team zusätzlich ein Link „NPCs verwalten“
  (eigene Seite `?action=npc-manage`).
- **Gesamtliste** (`jobliste.php?action=overview`): eigenständige Listenseite ohne Sidebar/Tabs,
  analog zur Familienliste des Relatives-Plugins optional im Rahmen der forumseigenen Listen:
  der Rahmen bekommt `$listen_content`/`$jobliste_overview_content`, `$listen_nav`/
  `$jobliste_list_nav`, `$headline`. Ohne Setting: Plugin-Template `jobliste_overview_page`.

## Hooks

| Hook | Handler | Zweck |
|---|---|---|
| `usercp_menu` | `jobliste_handle_usercp_menu` | Eigener, sauberer UserCP-Menüpunkt „Berufe" |
| `index_start` | `jobliste_handle_index_notice` | Roter Balken fuer offene Kategorie-Antraege (Team) |
| `modcp_nav` | `jobliste_handle_modcp_nav` | ModCP-Menuepunkt (muss hier gesetzt werden – modcp.php wertet `modcp_nav` vor `modcp_start` aus) |
| `modcp_start` | `jobliste_handle_modcp` | ModCP-Seite `action=jobliste_requests` |
| `relatives_collect_person_details` | `jobliste_provide_relatives_person_details` | Provider-Listener fuer Relatives (gefeuert in `Relatives_Database::person_details()`): setzt `$args['details'][person_id]['job']` als Klartext. Gespielte Charaktere (`linked_uids`) → Berufe des Accounts (`entity_type='user'`), sonst `entity_type='relative'` + person_id. Mehrere Berufe mit `", "` (nach id), Text via `jobliste_job_text()` (Klartext-Gegenstueck zu `jobliste_job_html()`). Eine Query fuer alle Personen, nur `jobliste_*`. Nur bei `jobliste_relatives_integration`; kaputte Args = still `return`, wirft nie |
| `relatives_person_form_fields` | `jobliste_relatives_person_form_fields` | Beruf-Felder im Personenformular von Relatives (zwischen „Herkunft“ und „Als gesucht markieren“): Template `jobliste_relatives_fields` (+ `_levels`, `_readonly`), Kombo-Feld wie `jobliste_render_form()`, aber ohne `<form>` und mit Praefix-Namen `jobliste_category_id`/`jobliste_custom_label`/`jobliste_level[...]` + Hidden `jobliste_submitted=1`, `jobliste_person_id`, `jobliste_job_id`. Keine Kategorien = nichts. Fremder gespielter Charakter oder erster Beruf ohne Kategorie (Altdaten) = nur Anzeige |
| `relatives_person_validate` | `jobliste_relatives_person_validate` | Nur bei `jobliste_submitted=1` (Stammbaum-Schnellformular postet ohne). Leere Auswahl = ok (entfernen). Sonst `jobliste_validate_job_input()` mit Praefix `jobliste_`; Fehler als Rohtext in `$args['errors']` |
| `relatives_person_saved` | `jobliste_relatives_person_saved` | Nur bei `jobliste_submitted=1`: bearbeiteten (= ersten) Beruf loeschen/aendern, sonst neu anlegen – aber nur, wenn die Entitaet noch keinen hat (Beitritt zu bestehender Person: ergaenzen, nie ueberschreiben). Rechte nach `clear_cache()` erneut geprueft |
| `datahandler_user_delete_start` | `jobliste_handle_user_delete` | Löscht `jobliste_jobs`-Zeilen mit `entity_type='user' AND entity_id = gelöschter uid`; setzt `created_by_uid=0` bei allen Zeilen, die dieser User als Team-Mitglied angelegt hat (NPC-Einträge bleiben erhalten) |

### Beruf im Personenformular von Relatives (Regeln)
- Zuordnung wie bei `relatives_collect_person_details`: `linked_uid > 0` → `user` + linked_uid,
  sonst `relative` + person_id (bei Neuanlage erst im Saved-Hook bekannt).
- Rechte (`jobliste_relatives_can_set_job()`): Team immer; `user` nur der eigene Account;
  `relative` ueber `Jobliste_EntityResolver::can_manage_entity()` (bei Neuanlage/Beitritt erst
  nach dem Speichern geprueft, dann ist die Person die eigene). Relatives prueft das
  Bearbeitungsrecht der Person zusaetzlich selbst, `verify_post_check()` macht Relatives.
- Mehrere Berufe je Entitaet: nur der erste (aelteste, `jobliste_job_id`) wird bearbeitet,
  weitere bleiben unangetastet (Hinweis + Link zur Jobliste). Eine `jobliste_job_id`, die nicht
  zur Entitaet gehoert, wird ignoriert.
- Kein Antrags-/Freigabeweg fuer Berufe – nur Kategorien werden beantragt (Link zum
  Antragsabschnitt der Jobliste im Feld).
- Gemeinsame Formular-Bausteine in `core/FormFields.php` (Praefix-Parameter), damit beide
  Formulare dieselbe Validierung nutzen. `jobliste.css` haengt auch an `relatives.php`;
  `jobliste.js` begrenzt das Kombo-Feld auf `[data-jobliste-scope]`.

## Eigene Hooks / API (Provider-Vertrag, Konsument = Jobliste selbst)

Diese beiden Hooks werden **von Jobliste definiert und gefeuert** (nicht von Relatives) – reines
Provider-Hook-Muster aus `conventions.md` §7, die Bringschuld liegt beim Provider (Relatives),
Jobliste braucht null Codeänderung wenn Relatives fehlt/deaktiviert ist:

```php
// beim Rendern des "Beruf hinzufügen"-Formulars:
$hook_args = ['uid' => $mybb->user['uid'], 'entities' => [
    ['entity_type' => 'user', 'entity_id' => $mybb->user['uid'], 'label' => $mybb->user['username']],
]];
$plugins->run_hooks('jobliste_collect_entities', $hook_args);
// Relatives hängt (wenn aktiv + jobliste_relatives_integration=1) eigene Zeilen an $hook_args['entities'] an.

// beim Rendern von Listen/Übersicht, pro Job-Zeile:
$hook_args = ['entity_type' => $job['entity_type'], 'entity_id' => $job['entity_id'], 'label' => null, 'relations' => [], 'linked_uid' => 0];
$plugins->run_hooks('jobliste_resolve_entity_label', $hook_args);
$label = $hook_args['label'] ?? jobliste_fallback_label($job); // z.B. "Verwandte*r (nicht gefunden)"
// optional: $hook_args['relations'] = [['uid' => 12, 'relation_label' => 'Onkel'], ...]
// = wer die Person eingetragen hat + Grad; Gesamtübersicht zeigt das im Info-Icon.
// optional: $hook_args['linked_uid'] = 34 = bestätigt verknüpfter Charakter-Account. Die
// Gesamtübersicht zeigt die Person dann als diesen Account (Profil-Link, Gruppenfarbe) mit
// Info-Icon; hat der Account eigene Berufe, werden die Verwandten-Berufe der Person ausgeblendet.
```

**Vertragsregeln** (aus `conventions.md` §7, auch im README zu dokumentieren): jede von einem
Provider gelieferte Zeile durch eine `jobliste_sanitize_entity()`-Funktion schicken (Typ-Casts,
Wertebereichs-Validierung), malformte Provider-Antworten **still verwerfen** (ein kaputter
Provider darf die Jobliste-Seite nie brechen), Escaping macht ausschließlich Jobliste beim
Rendern (Provider liefern Rohtext). Testbar mit einem Wegwerf-Provider-Testplugin auf dem
Testserver (`testing.md` §7).

## Abhängigkeiten
- Keine Pflicht-Abhängigkeiten – Plugin funktioniert komplett eigenständig (nur `entity_type=
  'user'`/`'npc'`, keine Verwandten-Auswahl, wenn Relatives fehlt).
- **Optionale Abhängigkeit:** `relatives` (Repo `F:\RPG\Mybb Plugins - eigene\relatives`) –
  liefert über die o.g. Hooks Verwandten-Entitäten. Erkennung NICHT über Plugin-Cache-Check
  nötig (reines Hook-Feuern, harmlos wenn niemand zuhört), aber `jobliste_relatives_integration`
  erlaubt zusätzliches admin-seitiges Abschalten. Integration aus = die Provider-Hooks werden gar nicht gefeuert; vorhandene
  Verwandten-Berufe bleiben in der DB erhalten, werden im Frontend aber weder gelistet noch sind
  Verwandte auswählbar (ACP-Moderation zeigt sie weiter, mit Fallback-Label). Verwandten-Berufe,
  deren Person Relatives nicht (mehr) kennt – Plugin inaktiv oder Person dort gelöscht –,
  blendet die Gesamtliste aus (`Jobliste_EntityResolver::is_resolved()`); die ACP-Moderation
  zeigt sie mit dem Fallback-Label „Verwandte*r (nicht gefunden)“.
- **Verwaiste Verwandten-Berufe** (Person von Relatives gelöscht): Relatives meldet Löschungen
  nicht, Jobliste liest keine `relatives_*`-Tabellen. Erkennung daher über den Label-Hook:
  `find_orphaned_relative_jobs()` = Relatives antwortet (`jobliste_relatives_provider_active()`:
  Integration an, `relatives_enabled`, Plugin-Cache aktiv, Listener registriert), kennt die
  Person aber nicht. Aufräumen nur manuell per Button in der ACP-Moderation (POST). Entscheidung
  mit dem Team: Personen mit wichtigem Beruf, die ihre Charaktere überdauern sollen, trägt das
  Team als NPC ein – keine automatische Umwandlung in NPCs.
- **Umsetzungsreihenfolge:** Dieses Plugin sollte **nach** `relatives` umgesetzt werden. Die
  Migration (Schritt 3, siehe unten) setzt zwingend voraus, dass `relatives` bereits installiert
  und migriert ist (liest `relatives_persons.legacy_id`/`legacy_source`).

## Berechtigungen
- Alle Aktionen erfordern Login.
- Eigene Jobs (eigener Account) anlegen/bearbeiten/löschen: nur der Owner selbst.
- Jobs für Verwandte anlegen: nur wer über `jobliste_collect_entities` als berechtigt geliefert
  wird (= Relatives liefert nur die eigenen `owner_uid`-Personen des anfragenden Users).
- NPC-Einträge anlegen sowie **fremde** Job-Einträge bearbeiten/löschen (Moderation): nur
  Mitglieder der in `jobliste_team_usergroups` konfigurierten Gruppen.
- Kategoriebaum/NPC-Verwaltung/Migrations-Tool: nur über ACP (Admin-Rechte).

## Migration (Legacy → Neu)
Quellen (Live-DB, `table_exists()`-Guard vor Zugriff): `mybb_relatives_items`, `mybb_jobs`,
`mybb_relatives`, `mybb_wanted` (letztere zwei nur für die `beruf`-Spalte). Re-Run-Schutz über
Setting/Flag `jobliste_legacy_migrated` (erneuter Lauf ohne „force“ = No-Op mit Warnhinweis; mit „force“ werden nur fehlende Einträge ergänzt – Kategorien über `legacy_item_id`, User-Berufe über `legacy_job_id`, Verwandten-Berufe über identische Einträge – nie dupliziert). **Migrations-Tool prüft vor Schritt 3 zwingend per
`table_exists()`, ob `relatives_persons` existiert** (= Relatives-Plugin bereits installiert
und migriert) – ohne das bricht der Lauf mit klarer Fehlermeldung ab, statt unvollständige Daten
zu erzeugen.

1. `relatives_items` (`id, name, parent, next`) → `jobliste_categories`: adjacency-list direkt
   übernehmbar (`parent_id = parent`), `allow_custom_text = (next == 2 ? 1 : 0)`,
   `legacy_item_id = alte id`. Temporäre Mapping-Tabelle `alte_id → neue id` für Schritt 2/3.
2. `mybb_jobs` (`id, uid, value`) → `jobliste_jobs` mit `entity_type='user'`, `entity_id=uid`:
   `value` wie im Altcode per `explode("\n", $value)` in Segmente zerlegen (leere Segmente
   ignorieren), numerische Segmente über die Kategorie-Mapping-Tabelle aus Schritt 1 auf
   `category_id` auflösen (tiefstes aufgelöstes Segment gewinnt als finale Kategorie; das erste
   Segment ist die Root-Auswahl GB/nicht GB/Muggel und wird mit aufgelöst, `0` = „Keine Angabe“
   → Eintrag wird übersprungen). Nicht-numerische Segmente danach → `custom_label`.
   **Sonderformat „nicht GB“/„Muggel“** (Altcode `functions_listen.php`: `$beruf[0] == 2 || 7`,
   z.B. `7|GB|Anderes|Militärarzt`): Root-ID gefolgt von Freitext-Land, Freitext-Sektor und
   Tätigkeit. Die Migration erkennt solche Roots an den Daten (numerisches Segment 0 direkt
   gefolgt von ≥ 3 Freitext-Segmenten), stellt sie automatisch auf Typ `levels` um (Ebene 1
   „Land“ = Profilfeld, im Migrations-Formular wählbar und mit dem Herkunfts-Profilfeld
   vorbelegt – ohne Profilfeld als ACP-Liste; Ebene 2 „Sektor“ = ACP-Liste, befüllt aus den
   Altdaten; `allow_custom_text = 1`) und speichert Land/Sektor in `level_values`, den Rest als
   `custom_label`. `legacy_job_id = alte id`. (Mit der Nutzerin abgestimmt, Phase 2.)
3. `mybb_relatives.beruf` / `mybb_wanted.beruf` (gleiches Pfad-Format wie `mybb_jobs.value`) →
   `jobliste_jobs` mit `entity_type='relative'`: `entity_id` über
   `relatives_persons.legacy_id = alte relatives/wanted-id AND legacy_source = 'relatives'|
   'wanted'` auflösen (Lookup in der bereits migrierten `relatives_persons`-Tabelle). Gleiches
   Pfad-Parsing wie Schritt 2.
4. Abschlussbericht: Anzahl importierter Kategorien/Jobs, Anzahl nicht auflösbarer
   `beruf`-Referenzen (verwaiste alte IDs) zur manuellen Prüfung.

## What NOT to change
- Kategoriebaum-Ownership bleibt bei Jobliste, nicht zurück zu Relatives verschieben (bewusste
  Entscheidung aus der Planungsphase, vermeidet die alte Code-Duplikation
  `mapUserToTree()`/`mapBerufToTree()`).
- `entity_type`/`entity_id`-Schema nicht auf separate Spalten pro Typ (z.B. `uid` +
  `relative_id` + `npc_id` nebeneinander) zurückbauen – das war explizit die im Audit
  empfohlene Vereinheitlichung.
- Provider-Hook-Vertrag (`jobliste_collect_entities`/`jobliste_resolve_entity_label`) nicht durch
  direkten Tabellenzugriff auf `relatives_*` ersetzen, auch nicht „aus Performance-Gründen" –
  das entkoppelt die Plugins bewusst in beide Richtungen.
- `legacy_item_id`/`legacy_job_id` nicht entfernen – Nachvollziehbarkeit bei Support-Anfragen zu
  migrierten Altdaten.
