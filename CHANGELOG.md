# Changelog

All notable changes to this plugin are documented here. Format based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow
[Semantic Versioning](https://semver.org/).

## [2.0.0] – unreleased

Complete rewrite of the legacy job plugin (*h4w-multiple-berufe* / *jobs*, v1.x). First public
release of the new code base.

### Fixed
- **UserCP menu icon follows the link colour.** The icon is an inline SVG using `currentColor` instead of a fixed grey background image.

### Security (review)
- ACP CSRF protection uses MyBB's native mechanism (dev kit `debugging.md` #23): write
  handlers only act on POST and `admin/index.php` validates the `my_post_key` itself –
  no own key check or helper.

### Changed (review)
- German help texts no longer use the colon gender form (“Nutzer:innen”, “Lehrer:in”).
- Plugin website points to `pand0rica/mybb-jobliste`.

### Added
- Own category tree (places/businesses) managed in the ACP: add, edit, delete, reorder,
  activate/deactivate, "free text allowed" per category. Replaces the legacy linked-list table
  that technically belonged to the relatives plugin (`next = 2` magic number → explicit flag).
- Category type *Levels*: instead of manual sub-categories an ordered list of levels whose
  options come from a selection profile field or an ACP-maintained list (e.g. "Muggles › GB ›
  Other"); the overview groups such categories dynamically by the values in use.
- One entity model for all jobs (`entity_type` + `entity_id`): own character, relatives
  (optional, via the Relatives plugin) and NPCs managed by the team.
- Simplified job form: one searchable category field instead of the legacy multi-step cascading
  selects.
- Collapsible, searchable overview of all jobs with one tab per root category, job counts,
  expand/collapse all, profile links in group colour, NPC badge and an info popup showing who
  added a relative in which degree. Optional access for guests and optional embedding into the
  forum's own list frame templates.
- NPC management for the team, in the frontend and in the ACP; in the ACP the jobs are picked,
  edited and removed directly in the NPC form.
- Category requests: members request missing categories in the User CP, the team reviews them in
  the ModCP (approve with adjustments or reject with reason), red notice on the index for open
  requests, optional PM on decision (sent natively so MyAlerts / Account Switcher react).
- ACP job moderation with filters (type, category, free-text search) and pagination.
- ACP migration from the legacy tables (`relatives_items`, `jobs`, `relatives.beruf`,
  `wanted.beruf`) with report, re-run protection and a *force* mode that only adds missing
  entries; legacy "not GB" / "Muggles" entries are converted to the *Levels* category type.
- Provider hooks `jobliste_collect_entities` and `jobliste_resolve_entity_label` (incl. optional
  `relations` and `linked_uid`) so other plugins can supply entities; every provider row is
  sanitised, malformed rows are silently discarded.
- Listener for the Relatives hook `relatives_collect_person_details`: Relatives shows each
  person's job (family tree panel, "My relatives", profile search cards). Played characters get
  the jobs of their linked account, all other persons their relative jobs; several jobs are
  joined with ", ". New plain-text helper `jobliste_job_text()`.
- Job field in the person form of the Relatives plugin (listeners for
  `relatives_person_form_fields`, `relatives_person_validate`, `relatives_person_saved`): the job
  of a person can be set, changed or removed right where the person is created or edited. Same
  category combo box, level fields and free-text rules as in the job list (own input names with
  the prefix `jobliste_`); only the first job of a person is edited there, further jobs stay
  untouched. Played characters can only be changed by their own account or the team. The quick
  form of the family tree (without `jobliste_submitted`) never touches jobs.
- Setting *Maximum jobs per person* (abuse protection) and *Relatives integration* switch.
- Account deletion is handled via `datahandler_user_delete_start` (all deletion paths): own jobs
  and requests are removed, entries created as team member are kept.
- English and German (`deutsch_du`) language files, separate frontend and ACP sets.
- CLI lifecycle test and self-test (`tests/`).

### Changed
- User CP menu entry is baked statically into the *Miscellaneous* box (`usercp_nav_misc`) with
  an inline icon; `usercp.css` is attached to `jobliste.php` so the sidebar keeps its styling.
- Settings are created on install and removed on uninstall only – deactivating/reactivating the
  plugin no longer resets them.
- Form helpers `jobliste_category_options()`, `jobliste_level_fields()` and
  `jobliste_validate_job_input()` moved from `jobliste.php` to `core/FormFields.php` (with an
  optional input name prefix) so the Relatives person form uses the same rules. `jobliste.css`
  is now also attached to `relatives.php`; `jobliste.js` scopes the combo box to
  `[data-jobliste-scope]` when it sits inside a foreign form.
- All frontend actions (edit, delete, withdraw, review, add job) are real buttons
  (`input.button`) instead of a mix of links and buttons; in the overview of all jobs, editing
  is a small pencil icon per entry.

### Fixed
- Legacy plugin: broken cascading selects when adding a job; fixed-height nested overview table
  that became unusable with many entries.
- ACP moderation and NPC list did not show the level values of jobs in *Levels* categories.
- Jobs of relatives that the Relatives plugin no longer knows (person deleted there) were still
  listed in the overview as "Relative (plugin inactive)". They are now hidden; the ACP job
  moderation offers **Delete orphaned jobs** (only while Relatives is active). Fallback label
  renamed to "Relative (not found)".
- `jobliste_job_html()` declared `global $lang` inside a branch instead of at the function start
  (`debugging.md` #7).
- ACP confirm dialogs: JavaScript string is now built with `json_encode()` instead of
  `addslashes()`.

### Security
- All write actions use POST + `verify_post_check()`; input only via `$mybb->get_input()`,
  output escaped with `htmlspecialchars_uni()`, SQL values escaped or cast to `int`.
- Permission checks for every action (owner, relatives delivered for the user, team, ModCP).
