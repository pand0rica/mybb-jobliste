# Jobliste – Agent Instructions

Dieses Repo wird mit dem **MyBB Plugin Dev Kit** entwickelt. Bevor du hier etwas tust:

1. Lies zuerst das Dev-Kit: **`<dev-kit>/AGENTS.md`** und halte dich an alle dort
   verlinkten Standards. Das Kit ist die kanonische Quelle für Konventionen, Boilerplate,
   Debugging und Testing.
2. Lies dann in diesem Repo: `PROJECT_CONTEXT.md` (Kontext) und – in der Umsetzungsphase –
   `IMPLEMENTATION_PLAN.md` (die Arbeitsschritte).

Das Dev-Kit wird **nicht** in dieses Repo kopiert; es wird per absolutem Pfad referenziert.

## Sprache
Antworten Deutsch, Code/Bezeichner Englisch. PHP 7.4 + 8.x. Keine externen Libraries.

## Schwester-Plugin
Dieses Plugin ist Teil einer 3-Plugin-Familie (Rework von zwei Altplugins). Es integriert sich
**optional** mit `mybb-relatives` über die Provider-Hooks
`jobliste_collect_entities` / `jobliste_resolve_entity_label`, die dieses Plugin selbst definiert
und feuert (siehe `PROJECT_CONTEXT.md`). Relatives muss **vor** der Jobliste-Migration bereits
umgesetzt und migriert sein (die Jobliste-Migration löst Legacy-Beruf-Daten über
`relatives_persons.legacy_id` auf).

## Phasen (der Nutzer löst sie einzeln aus)

| Nutzer sagt | Phase | Folge dieser Kit-Datei |
|---|---|---|
| „umsetzen / implementieren" | Umsetzung | `<dev-kit>/workflows/2-implement.md` + `IMPLEMENTATION_PLAN.md` |
| „reviewe das Projekt" | Review | `<dev-kit>/workflows/3-review.md` |
| „release das Projekt" | Release | `<dev-kit>/workflows/4-release.md` |

## Die 5 goldenen Regeln (Kurzform, Details im Kit)
Keine Core-Edits · `TABLE_PREFIX` · `$mybb->get_input()` · POST + `verify_post_check()` (Frontend; im ACP nur POST-Pflicht, Key prüft MyBB) ·
alles mit Präfix `jobliste`. Ausgabe `htmlspecialchars_uni()`, SQL `escape_string()`/`(int)`.
