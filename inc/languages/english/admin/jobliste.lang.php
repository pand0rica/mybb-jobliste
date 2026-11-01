<?php
/**
 * Jobliste - admin language file (english)
 */

$l['jobliste'] = "Job List";
$l['jobliste_acp_title'] = "Job List";

// Settinggroup
$l['setting_group_jobliste'] = "Job List";
$l['setting_group_jobliste_desc'] = "Settings for the job list (jobs of characters, relatives and NPCs).";

// Tabs
$l['jobliste_tab_categories'] = "Category tree";
$l['jobliste_tab_desc_categories'] = "Create, nest and sort workplaces and branches and decide where free text is allowed.";
$l['jobliste_tab_npcs'] = "Manage NPCs";
$l['jobliste_tab_desc_npcs'] = "Create, edit and delete NPCs without an account and manage their jobs. The team can also add jobs in the frontend under Job List → Manage NPCs.";
$l['jobliste_tab_moderation'] = "Job moderation";
$l['jobliste_tab_desc_moderation'] = "Search, filter and, if necessary, delete all job entries.";
$l['jobliste_tab_migration'] = "Migration";
$l['jobliste_tab_desc_migration'] = "One-time import from the old jobs plugin (relatives_items, jobs, relatives.beruf, wanted.beruf).";

// General
$l['jobliste_acp_actions_title'] = "Actions";
$l['jobliste_acp_edit'] = "Edit";
$l['jobliste_acp_delete'] = "Delete";
$l['jobliste_acp_inactive'] = "inactive";
$l['jobliste_acp_not_found'] = "The entry could not be found.";
$l['jobliste_acp_error_generic'] = "The action could not be carried out.";
$l['jobliste_acp_no_category'] = "Without category";
$l['jobliste_acp_no_jobs'] = "No jobs";
$l['jobliste_acp_legacy'] = "legacy";
$l['jobliste_acp_available'] = "available";
$l['jobliste_acp_missing'] = "missing";

// Entity types + fallback labels (also needed in the ACP)
$l['jobliste_type_user'] = "Character";
$l['jobliste_type_relative'] = "Relative";
$l['jobliste_type_npc'] = "NPC";
$l['jobliste_fallback_user'] = "Deleted account";
$l['jobliste_fallback_npc'] = "Deleted NPC";
$l['jobliste_fallback_relative'] = "Relative (not found)";
$l['jobliste_fallback_unknown'] = "Unknown";

// Category tree
$l['jobliste_acp_categories_overview'] = "Category tree";
$l['jobliste_acp_add_category'] = "Add category";
$l['jobliste_acp_add_category_desc'] = "Add a new top-level category. Add sub-categories directly via \"Sub-category\" in the respective row.";
$l['jobliste_acp_edit_category'] = "Edit category";
$l['jobliste_acp_add_child'] = "Sub-category";
$l['jobliste_acp_edit_levels'] = "Edit levels";
$l['jobliste_acp_no_categories'] = "No categories have been created yet.";
$l['jobliste_acp_col_name'] = "Name";
$l['jobliste_acp_col_type'] = "Type";
$l['jobliste_acp_col_custom_text'] = "Free text";
$l['jobliste_acp_col_active'] = "Active";
$l['jobliste_acp_col_jobs'] = "Jobs";
$l['jobliste_acp_col_order'] = "Order";
$l['jobliste_acp_col_actions'] = "Actions";
$l['jobliste_acp_field_name'] = "Name";
$l['jobliste_acp_field_parent'] = "Parent category";
$l['jobliste_acp_field_parent_desc'] = "Where in the tree should this category be placed? Categories of type \"Levels\" cannot have sub-categories.";
$l['jobliste_acp_parent_root'] = "(top level)";
$l['jobliste_acp_field_type'] = "Type";
$l['jobliste_acp_field_type_desc'] = "<strong>Tree:</strong> all sub-categories are created here by hand.<br /><strong>Levels:</strong> no sub-categories; instead you define levels (e.g. country, sector) whose options come from a profile field or a list.";
$l['jobliste_acp_type_tree'] = "Tree";
$l['jobliste_acp_type_levels'] = "Levels";
$l['jobliste_acp_field_allow_custom_text'] = "Allow free text?";
$l['jobliste_acp_field_allow_custom_text_desc'] = "May users additionally enter an exact position as free text for this category (e.g. \"Potions teacher\")?";
$l['jobliste_acp_field_is_active'] = "Active?";
$l['jobliste_acp_field_is_active_desc'] = "Inactive categories (and everything below them) can no longer be selected. Existing entries are kept and still shown.";
$l['jobliste_acp_category_saved'] = "The category has been saved.";
$l['jobliste_acp_category_saved_levels'] = "The category has been saved. Now define its levels.";
$l['jobliste_acp_category_deleted'] = "The category has been deleted.";
$l['jobliste_acp_category_deleted_moved'] = "The category has been deleted. {1} jobs were moved to the parent category.";
$l['jobliste_acp_confirm_delete_category'] = "Really delete this category? Jobs assigned to it move to the parent category.";
$l['jobliste_acp_error_name'] = "Please enter a name.";
$l['jobliste_acp_error_parent'] = "The parent category does not exist.";
$l['jobliste_acp_error_cycle'] = "A category cannot be moved below itself or below one of its own sub-categories.";
$l['jobliste_acp_error_has_children'] = "This category still has sub-categories. Delete or move them first.";
$l['jobliste_acp_error_levels_has_children'] = "A category with sub-categories cannot be switched to type \"Levels\". Move or delete the sub-categories first.";
$l['jobliste_acp_error_parent_levels'] = "Categories of type \"Levels\" cannot have sub-categories.";

// Levels
$l['jobliste_acp_levels_title'] = "Levels of \"{1}\"";
$l['jobliste_acp_levels_none'] = "no levels defined yet";
$l['jobliste_acp_levels_not_levels_type'] = "This category is of type \"Tree\". The levels are only used once you switch its type to \"Levels\".";
$l['jobliste_acp_add_level'] = "Add level";
$l['jobliste_acp_edit_level'] = "Edit level";
$l['jobliste_acp_col_level'] = "Level";
$l['jobliste_acp_col_source'] = "Source";
$l['jobliste_acp_col_options'] = "Options";
$l['jobliste_acp_field_level_label'] = "Label";
$l['jobliste_acp_field_level_label_desc'] = "Shown above the select field in the form, e.g. \"Country\" or \"Sector\".";
$l['jobliste_acp_field_source'] = "Source of the options";
$l['jobliste_acp_field_source_desc'] = "<strong>Profile field:</strong> the options of a choice profile field (e.g. country of origin) – changes to the profile field apply immediately.<br /><strong>List:</strong> the values you enter below.";
$l['jobliste_acp_source_profilefield'] = "Profile field";
$l['jobliste_acp_source_list'] = "List";
$l['jobliste_acp_field_profilefield'] = "Profile field";
$l['jobliste_acp_field_profilefield_desc'] = "Only for source \"Profile field\". Only profile fields with fixed options (select, radio buttons, multi-select, checkboxes) are offered.";
$l['jobliste_acp_profilefield_choose'] = "– choose a profile field –";
$l['jobliste_acp_profilefield_missing'] = "profile field no longer exists";
$l['jobliste_acp_field_options'] = "List";
$l['jobliste_acp_field_options_desc'] = "Only for source \"List\": one entry per line, e.g. the sectors.";
$l['jobliste_acp_level_no_options'] = "none";
$l['jobliste_acp_level_saved'] = "The level has been saved.";
$l['jobliste_acp_level_deleted'] = "The level has been deleted.";
$l['jobliste_acp_confirm_delete_level'] = "Really delete this level? Values already stored on jobs are kept.";
$l['jobliste_acp_error_level'] = "The level could not be saved. Please enter a label and, for source \"Profile field\", choose a valid profile field.";

// NPCs
$l['jobliste_acp_npcs_overview'] = "NPCs";
$l['jobliste_acp_add_npc'] = "Add NPC";
$l['jobliste_acp_add_npc_desc'] = "Add a new NPC – you can pick its job right in the form. Add further jobs via \"Edit\"; the team can also add them in the frontend under Job List → Manage NPCs.";
$l['jobliste_acp_edit_npc'] = "Edit NPC";
$l['jobliste_acp_no_npcs'] = "No NPCs have been created yet.";
$l['jobliste_acp_field_npc_name'] = "Name";
$l['jobliste_acp_field_npc_notes'] = "Notes";
$l['jobliste_acp_field_npc_notes_desc'] = "Optional, only visible to the team.";
$l['jobliste_acp_col_created_by'] = "Created by";
$l['jobliste_acp_npc_saved'] = "The NPC has been saved.";
$l['jobliste_acp_npc_deleted'] = "The NPC and all of its jobs have been deleted.";
$l['jobliste_acp_confirm_delete_npc'] = "Really delete this NPC including all of its jobs?";
$l['jobliste_acp_error_npc_name'] = "Please enter a name for the NPC.";
$l['jobliste_acp_no_job_option'] = "– no job –";
$l['jobliste_acp_field_job'] = "Job";
$l['jobliste_acp_field_job_n'] = "Job {1}";
$l['jobliste_acp_field_job_new'] = "Add another job";
$l['jobliste_acp_field_job_desc'] = "Optional. Type into the search field to filter the list, then pick an entry.";
$l['jobliste_acp_field_job_existing_desc'] = "Choose \"– no job –\" to remove this job when saving.";

// Moderation
$l['jobliste_acp_filter_title'] = "Filter";
$l['jobliste_acp_filter_type'] = "For whom";
$l['jobliste_acp_filter_category'] = "Category";
$l['jobliste_acp_filter_search'] = "Search";
$l['jobliste_acp_filter_search_desc'] = "Searches the exact position (free text) and the level values.";
$l['jobliste_acp_filter_submit'] = "Filter";
$l['jobliste_acp_filter_all'] = "All";
$l['jobliste_acp_jobs_overview'] = "Jobs ({1})";
$l['jobliste_acp_no_jobs_found'] = "No jobs found.";
$l['jobliste_acp_col_id'] = "ID";
$l['jobliste_acp_col_entity'] = "Person";
$l['jobliste_acp_col_job'] = "Job";
$l['jobliste_acp_col_updated'] = "Last changed";
$l['jobliste_acp_job_deleted'] = "The job has been deleted.";
$l['jobliste_acp_confirm_delete_job'] = "Really delete this job?";

// Migration
$l['jobliste_acp_migration_status'] = "Legacy data status";
$l['jobliste_acp_migration_last_run'] = "Last migration";
$l['jobliste_acp_migration_never'] = "never";
$l['jobliste_acp_migration_run_title'] = "Run migration";
$l['jobliste_acp_migration_run'] = "Run migration";
$l['jobliste_acp_migration_profilefield'] = "Profile field for the level \"Country\"";
$l['jobliste_acp_migration_profilefield_desc'] = "Categories in the old special format (e.g. \"Muggel\", \"nicht GB\" with country and sector as free text) are switched to type \"Levels\". The level \"Country\" takes its options from this profile field, the level \"Sector\" gets a list built from the legacy data. Without a profile field, \"Country\" becomes a list from the legacy data as well.";
$l['jobliste_acp_migration_no_profilefield'] = "– no profile field (list from legacy data) –";
$l['jobliste_acp_migration_force'] = "Run again (force)?";
$l['jobliste_acp_migration_force_desc'] = "The migration has already been run. Running it again only adds missing entries and never creates duplicates.";
$l['jobliste_acp_migration_already_done'] = "The migration has already been run. Set \"Run again (force)\" to Yes to add missing entries.";
$l['jobliste_acp_migration_no_items'] = "The legacy table relatives_items is missing – there is nothing to migrate.";
$l['jobliste_acp_migration_no_relatives'] = "The Relatives plugin is not installed or not migrated yet (table relatives_persons is missing). Install and migrate the Relatives plugin first, otherwise the jobs of relatives cannot be assigned.";
$l['jobliste_acp_migration_level_country'] = "Country";
$l['jobliste_acp_migration_level_sector'] = "Sector";
$l['jobliste_acp_report_title'] = "Migration result";
$l['jobliste_acp_report_categories_created'] = "Categories imported";
$l['jobliste_acp_report_categories_existing'] = "Categories already present";
$l['jobliste_acp_report_level_categories'] = "Switched to type \"Levels\"";
$l['jobliste_acp_report_jobs_user'] = "Character jobs imported";
$l['jobliste_acp_report_jobs_relative'] = "Relative jobs imported";
$l['jobliste_acp_report_jobs_existing'] = "Jobs already present (skipped)";
$l['jobliste_acp_report_skipped_empty'] = "Empty entries / \"no answer\" (skipped)";
$l['jobliste_acp_report_skipped_orphan_users'] = "Jobs of deleted accounts (skipped)";
$l['jobliste_acp_report_unresolved_relatives'] = "Relatives without a match in relatives_persons (please check manually)";
$l['jobliste_acp_report_unresolved_items'] = "Unknown legacy category IDs (please check manually)";

// Orphaned relative jobs (moderation)
$l['jobliste_acp_orphans_notice'] = "{1} job(s) belong to relatives that the Relatives plugin no longer knows (person deleted there). They are no longer shown in the overview.";
$l['jobliste_acp_orphans_cleanup'] = "Delete orphaned jobs";
$l['jobliste_acp_orphans_confirm'] = "Delete all jobs of relatives that no longer exist? This cannot be undone.";
$l['jobliste_acp_orphans_deleted'] = "{1} orphaned job(s) deleted.";
