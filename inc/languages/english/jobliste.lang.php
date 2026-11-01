<?php
/**
 * Jobliste - frontend language file (english)
 */

$l['jobliste'] = "Job List";
$l['jobliste_usercp_menu_item'] = "Jobs";
$l['jobliste_disabled'] = "The job list is currently disabled.";

// Navigation
$l['jobliste_nav_mine'] = "My jobs";
$l['jobliste_overview_link'] = "Go to the list of all jobs &raquo;";
$l['jobliste_nav_npcs'] = "Manage NPCs";

// Own jobs
$l['jobliste_my_jobs'] = "Jobs of your character and relatives";
$l['jobliste_no_jobs'] = "No jobs added yet.";
$l['jobliste_no_category'] = "Without category";
$l['jobliste_limit_note'] = "Each person can have at most {1} jobs.";
$l['jobliste_actions'] = "Actions";
$l['jobliste_edit'] = "Edit";
$l['jobliste_delete'] = "Delete";
$l['jobliste_delete_job'] = "Delete this job";
$l['jobliste_confirm_delete'] = "Really delete this job?";

// Entity types
$l['jobliste_type_user'] = "Character";
$l['jobliste_type_relative'] = "Relative";
$l['jobliste_type_npc'] = "NPC";
$l['jobliste_group_own'] = "Your character";
$l['jobliste_group_relatives'] = "Your relatives";
$l['jobliste_group_npcs'] = "NPCs (team)";

// Fallback labels (entity could not be resolved)
$l['jobliste_fallback_user'] = "Deleted account";
$l['jobliste_fallback_npc'] = "Deleted NPC";
$l['jobliste_fallback_relative'] = "Relative (not found)";
$l['jobliste_fallback_unknown'] = "Unknown";

// Form
$l['jobliste_add_title'] = "Add job";
$l['jobliste_add_submit'] = "Add job";
$l['jobliste_edit_title'] = "Edit job";
$l['jobliste_edit_submit'] = "Save changes";
$l['jobliste_field_entity'] = "For whom?";
$l['jobliste_field_category'] = "Workplace / job";
$l['jobliste_field_category_desc'] = "Type into the search field to filter the list, then pick an entry.";
$l['jobliste_field_custom_label'] = "Exact position";
$l['jobliste_field_custom_label_desc'] = "Optional, e.g. \"Potions teacher\". Only available for categories that allow free text.";
$l['jobliste_search_placeholder'] = "Search category …";
$l['jobliste_no_categories'] = "No categories have been created yet. The team can add them in the Admin CP under Tools & Maintenance → Job List.";

// Overview
$l['jobliste_overview_title'] = "Overview of all jobs";
$l['jobliste_overview_search'] = "Filter by name, place or job …";
$l['jobliste_overview_count'] = "{1} jobs listed.";
$l['jobliste_overview_empty'] = "There are no categories or jobs yet.";
$l['jobliste_expand_all'] = "Expand all";
$l['jobliste_collapse_all'] = "Collapse all";
$l['jobliste_filter_no_results'] = "Nothing matches this filter.";
$l['jobliste_overview_panel_empty'] = "No jobs have been added to this category yet.";
$l['jobliste_relinfo_title'] = "Who added this person?";
$l['jobliste_relinfo_head'] = "Added as a relative by:";
$l['jobliste_relinfo_none'] = "No details available.";

// NPCs
$l['jobliste_npc_name'] = "Name";
$l['jobliste_npc_notes'] = "Notes";
$l['jobliste_npc_notes_desc'] = "Optional, only visible to the team.";
$l['jobliste_npc_jobs'] = "Jobs";
$l['jobliste_npc_add_job'] = "+ Add job";
$l['jobliste_npc_add_title'] = "Add NPC";
$l['jobliste_npc_edit_title'] = "Edit NPC";
$l['jobliste_npc_save'] = "Save NPC";
$l['jobliste_no_npcs'] = "No NPCs added yet.";
$l['jobliste_confirm_delete_npc'] = "Really delete this NPC including all of its jobs?";
$l['jobliste_npc_saved'] = "The NPC has been saved.";
$l['jobliste_npc_deleted'] = "The NPC has been deleted.";

// Messages
$l['jobliste_saved'] = "The job has been saved.";
$l['jobliste_deleted'] = "The job has been deleted.";
$l['jobliste_error_not_found'] = "This entry does not exist (anymore).";
$l['jobliste_error_category_required'] = "Please pick a category.";
$l['jobliste_error_category_inactive'] = "This category can no longer be selected.";
$l['jobliste_error_custom_not_allowed'] = "This category does not allow an exact position (free text).";
$l['jobliste_error_limit'] = "This person already has {1} jobs – no more can be added.";
$l['jobliste_error_save'] = "The entry could not be saved.";
$l['jobliste_error_npc_name'] = "Please enter a name for the NPC.";
$l['jobliste_error_invalid_action'] = "Unknown action.";

// Levels (category type "levels")
$l['jobliste_field_levels'] = "Exact place";
$l['jobliste_field_levels_desc'] = "Pick an entry for every level.";
$l['jobliste_level_choose'] = "– please choose –";
$l['jobliste_error_levels_required'] = "Please pick a valid entry for every level (e.g. country and sector).";

// Category requests (UserCP)
$l['jobliste_request_disabled'] = "Requesting new categories is currently disabled.";
$l['jobliste_request_title'] = "Request a new category / workplace";
$l['jobliste_request_desc'] = "The team reviews your request and creates the category. You can see the status in the list of your requests above.";
$l['jobliste_request_field_name'] = "Name";
$l['jobliste_request_field_name_desc'] = "e.g. \"Leaky Cauldron\" or \"Ministry of Magic\"";
$l['jobliste_request_field_parent'] = "Place under";
$l['jobliste_request_field_parent_desc'] = "Which existing category should the new one belong to?";
$l['jobliste_request_field_note'] = "Reason / note";
$l['jobliste_request_field_note_desc'] = "Optional: what do you need the category for, what belongs to it?";
$l['jobliste_request_submit'] = "Send request";
$l['jobliste_request_parent_root'] = "– as a new top-level category –";
$l['jobliste_request_inactive_suffix'] = "(inactive)";
$l['jobliste_request_mine_title'] = "Your category requests";
$l['jobliste_request_none'] = "You have not requested any categories yet.";
$l['jobliste_request_col_category'] = "Category";
$l['jobliste_request_col_status'] = "Status";
$l['jobliste_request_col_response'] = "Team note";
$l['jobliste_request_col_date'] = "Date";
$l['jobliste_request_col_note'] = "Reason";
$l['jobliste_request_col_user'] = "Requested by";
$l['jobliste_request_status_pending'] = "Pending";
$l['jobliste_request_status_approved'] = "Approved";
$l['jobliste_request_status_rejected'] = "Rejected";
$l['jobliste_request_withdraw'] = "Withdraw";
$l['jobliste_request_confirm_withdraw'] = "Really withdraw this request?";
$l['jobliste_request_saved'] = "Your request has been sent to the team.";
$l['jobliste_request_withdrawn'] = "Your request has been withdrawn.";
$l['jobliste_request_error_name_required'] = "Please enter a name for the category.";
$l['jobliste_request_error_parent_invalid'] = "No new category can be created below this category.";
$l['jobliste_request_error_category_exists'] = "A category with this name already exists at this position.";
$l['jobliste_request_error_request_duplicate'] = "This category has already been requested and is waiting for the team.";
$l['jobliste_request_error_limit_reached'] = "You already have {1} pending requests. Please wait until the team has handled them.";
$l['jobliste_request_error_not_pending'] = "This request has already been handled or no longer exists.";
$l['jobliste_request_error_save_failed'] = "The request could not be saved.";

// Category requests (board index + ModCP)
$l['jobliste_request_notice_one'] = "<strong>Job list:</strong> 1 category request is waiting to be handled.";
$l['jobliste_request_notice_many'] = "<strong>Job list:</strong> {1} category requests are waiting to be handled.";
$l['jobliste_modcp_nav_requests'] = "Category requests";
$l['jobliste_modcp_title'] = "Job list: category requests";
$l['jobliste_modcp_pending_title'] = "Pending requests";
$l['jobliste_modcp_handled_title'] = "Recently handled";
$l['jobliste_modcp_col_handled'] = "Handled by";
$l['jobliste_modcp_no_pending'] = "No pending requests.";
$l['jobliste_modcp_no_handled'] = "No requests handled yet.";
$l['jobliste_modcp_review'] = "Review";
$l['jobliste_modcp_review_title'] = "Review category request";
$l['jobliste_modcp_approve_title'] = "Approve";
$l['jobliste_modcp_approve_desc'] = "Creates the category immediately as active. You can adjust name and position first; further settings (type, order) are in the Admin CP under Tools & Maintenance → Job list.";
$l['jobliste_modcp_field_custom'] = "Allow free text";
$l['jobliste_modcp_field_custom_desc'] = "May members additionally enter an exact job title for this category?";
$l['jobliste_modcp_field_custom_label'] = "Yes, allow an exact job title as free text";
$l['jobliste_modcp_field_response'] = "Note";
$l['jobliste_modcp_field_response_approve_desc'] = "Optional.";
$l['jobliste_modcp_field_response_reject_desc'] = "Why is the request rejected? Optional, but helpful.";
$l['jobliste_modcp_pm_note'] = "The requester receives it by PM.";
$l['jobliste_modcp_approve_submit'] = "Approve and create category";
$l['jobliste_modcp_reject_title'] = "Reject";
$l['jobliste_modcp_reject_submit'] = "Reject request";
$l['jobliste_modcp_approved'] = "The request has been approved and the category created.";
$l['jobliste_modcp_rejected'] = "The request has been rejected.";

// Category requests: PM to the requester (MyCode)
$l['jobliste_request_pm_subject_approved'] = "Category approved: {1}";
$l['jobliste_request_pm_subject_rejected'] = "Category rejected: {1}";
$l['jobliste_request_pm_message_approved'] = "Hello,\n\nyour request for a new category in the job list has been approved. It is called [b]{1}[/b] and can be selected right away.{2}\n\n[url={3}]Go to the job list[/url]";
$l['jobliste_request_pm_message_rejected'] = "Hello,\n\nunfortunately your request for the category [b]{1}[/b] in the job list has been rejected.{2}\n\n[url={3}]Go to the job list[/url]";
$l['jobliste_request_pm_response'] = "\n\n[b]Note from the team:[/b]\n{1}";

// Job field in the person form of the Relatives plugin
$l['jobliste_relatives_field_job'] = "Job";
$l['jobliste_relatives_field_job_desc'] = "Optional. Pick \"No job\" to remove it. If the person turns out to be a played character of another account, nothing is taken over here – that account maintains its own job.";
$l['jobliste_relatives_no_job'] = "– No job –";
$l['jobliste_relatives_request_link'] = "Category missing? Request it.";
$l['jobliste_relatives_more_jobs'] = "This person has {1} more job(s). You can manage them in the <a href=\"jobliste.php\">job list</a>; only the first job is edited here.";
$l['jobliste_relatives_readonly_hint'] = "This is a played character – only its own account (or the team) can change the job.";
$l['jobliste_relatives_readonly_manage'] = "This job can only be edited in the <a href=\"jobliste.php\">job list</a>.";
$l['jobliste_relatives_readonly_foreign'] = "You may not change the job of this person.";
