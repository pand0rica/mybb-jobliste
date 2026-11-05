# Jobliste – Start-Prompt für Phase 2 (Umsetzung)

Du setzt das MyBB-1.8-Plugin **Jobliste** (Codename `jobliste`) um. Das Umsetzungs-Paket aus
Phase 1 liegt bereits vollständig im Repo:

  mybb-jobliste

Lies in dieser Reihenfolge, BEVOR du irgendetwas tust:
1. `AGENTS.md` (dieses Repo) – Pointer aufs Dev-Kit, 5 goldene Regeln, Phasen-Zuordnung.
2. `PROJECT_CONTEXT.md` (dieses Repo) – Architektur, Tabellen, Settings, Hooks, Abhängigkeiten,
   „What NOT to change".
3. `IMPLEMENTATION_PLAN.md` (dieses Repo) – die nummerierten Arbeitsschritte.
4. Aus dem Dev-Kit bei Bedarf: `<dev-kit>/standards/boilerplate.md` (kopierfertige
   Code-Vorlagen) und `<dev-kit>/standards/conventions.md` (Naming, DB, Hooks,
   Security).

Folge danach exakt `<dev-kit>/workflows/2-implement.md`:
- Arbeite die Schritte aus `IMPLEMENTATION_PLAN.md` der Reihe nach ab und hake sie dort ab
  (`- [x]`), inklusive des jeweiligen Test-/Abnahmehinweises.
- Halte bei jedem Schritt die 5 goldenen Regeln ein: keine Core-Dateien ändern, `TABLE_PREFIX`
  statt `mybb_`, nur `$mybb->get_input()`, jede schreibende Aktion per POST +
  `verify_post_check()`, jede Funktion/jeder globale Bezeichner mit Präfix `jobliste_`.
  Ausgabe mit `htmlspecialchars_uni()`, SQL mit `escape_string()`/`(int)`.
- Bei Unklarheiten gezielt nachfragen – **nicht neu planen**, Struktur und Umfang aus
  `IMPLEMENTATION_PLAN.md` stehen fest. Größere Abweichungen (neue Tabelle, anderer Hook) mit
  dem Nutzer klären und danach den Plan-Eintrag aktualisieren, statt sie stillschweigend
  umzusetzen.
- Sprachdateien: englisch + deutsch_du, Frontend- und Admin-Sets strikt getrennt.

**Abhängigkeiten:** Optionale Integration mit `mybb-relatives` über die
in diesem Repo selbst definierten Provider-Hooks `jobliste_collect_entities`/
`jobliste_resolve_entity_label` – die Schritte 1–7 (Grundgerüst bis ACP) sind davon unabhängig
und können sofort begonnen werden. **Schritt 8 (Migration) setzt dagegen zwingend voraus, dass
`relatives` bereits umgesetzt UND auf dem Testforum migriert ist** (liest
`relatives_persons.legacy_id`/`legacy_source`, siehe `PROJECT_CONTEXT.md` „Migration") – prüfe
vor Schritt 8 kurz mit dem Nutzer, ob das schon der Fall ist, sonst diesen Schritt zurückstellen
und mit Schritt 9 (Provider-Hook-Test) fortfahren.

Schreib **keinen** Code, bevor du die drei Repo-Dateien oben gelesen hast. Antworten auf
Deutsch, Code/Bezeichner/Kommentare auf Englisch, PHP 7.4 + 8.x kompatibel, keine externen
Libraries.

## Abschluss
Wenn alle Schritte abgehakt sind: `php -l` sauber, CLI-Lifecycle-Test (install→uninstall→
install) beweist vollständigen Cleanup, kurzer Bericht was umgesetzt wurde / was noch offen ist
(z.B. manueller ACP-Klicktest) / bekannte Risiken. Begleitdateien (README, PLUGIN_POST.html,
CHANGELOG …) sind **nicht** Teil dieser Phase – das macht Phase 3 (Review), ausgelöst mit
„reviewe das Projekt".
