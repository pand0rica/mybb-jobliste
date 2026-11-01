<div class="relatives_field jobliste_relatives_fields" data-jobliste-scope="1">
	<input type="hidden" name="jobliste_submitted" value="1" />
	<input type="hidden" name="jobliste_person_id" value="{$jobliste_rel_person_id}" />
	<input type="hidden" name="jobliste_job_id" value="{$jobliste_rel_job_id}" />
	<label for="jobliste_rel_category">{$lang->jobliste_relatives_field_job}</label>
	<div class="smalltext jobliste_relatives_desc">{$lang->jobliste_relatives_field_job_desc}{$jobliste_rel_request_link}</div>
	<div class="jobliste_combo" data-jobliste-combo="1">
		<input type="search" class="textbox jobliste_combo_search" placeholder="{$lang->jobliste_search_placeholder}" aria-label="{$lang->jobliste_search_placeholder}" autocomplete="off" hidden="hidden" />
		<select name="jobliste_category_id" id="jobliste_rel_category" class="jobliste_combo_select" size="8"><option value="0" data-path="{$lang->jobliste_relatives_no_job}"{$jobliste_rel_none_selected}>{$lang->jobliste_relatives_no_job}</option>{$jobliste_rel_category_options}</select>
		<div class="jobliste_combo_selected smalltext" aria-live="polite"></div>
	</div>
	{$jobliste_rel_levels_row}
	<div class="jobliste_custom_row">
		<label for="jobliste_rel_custom_label">{$lang->jobliste_field_custom_label}</label>
		<div class="smalltext">{$lang->jobliste_field_custom_label_desc}</div>
		<input type="text" id="jobliste_rel_custom_label" name="jobliste_custom_label" class="textbox jobliste_custom_input" maxlength="255" value="{$jobliste_rel_custom_label}" />
	</div>
	{$jobliste_rel_more}
</div>
<script src="{$mybb->settings['bburl']}/inc/plugins/jobliste/assets/jobliste.js?v={$jobliste_js_version}"></script>
