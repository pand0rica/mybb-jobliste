<form action="jobliste.php" method="post" class="jobliste_form">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="{$form_action}" />
<input type="hidden" name="id" value="{$form_job_id}" />
<input type="hidden" name="return" value="{$jobliste_return}" />
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr>
	<td class="thead" colspan="2"><strong>{$form_title}</strong></td>
</tr>
<tr>
	<td class="trow1" width="30%"><strong>{$lang->jobliste_field_entity}</strong></td>
	<td class="trow1">{$form_entity_field}</td>
</tr>
<tr>
	<td class="trow2" valign="top"><strong>{$lang->jobliste_field_category}</strong><br /><span class="smalltext">{$lang->jobliste_field_category_desc}</span></td>
	<td class="trow2">
		<div class="jobliste_combo" data-jobliste-combo="1">
			<input type="search" class="textbox jobliste_combo_search" placeholder="{$lang->jobliste_search_placeholder}" aria-label="{$lang->jobliste_search_placeholder}" autocomplete="off" hidden="hidden" />
			<select name="category_id" class="jobliste_combo_select" size="10" required="required">{$form_category_options}</select>
			<div class="jobliste_combo_selected smalltext" aria-live="polite"></div>
		</div>
	</td>
</tr>
{$form_levels_row}
<tr class="jobliste_custom_row">
	<td class="trow1"><strong>{$lang->jobliste_field_custom_label}</strong><br /><span class="smalltext">{$lang->jobliste_field_custom_label_desc}</span></td>
	<td class="trow1"><input type="text" name="custom_label" class="textbox jobliste_custom_input" maxlength="255" value="{$form_custom_label}" /></td>
</tr>
</table>
<div class="jobliste_submit"><input type="submit" class="button" value="{$form_submit}" /></div>
</form>
