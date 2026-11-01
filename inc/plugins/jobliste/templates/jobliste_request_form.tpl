<form action="jobliste.php" method="post" class="jobliste_form">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="request-save" />
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr>
	<td class="thead" colspan="2"><strong>{$lang->jobliste_request_title}</strong><div class="smalltext">{$lang->jobliste_request_desc}</div></td>
</tr>
<tr>
	<td class="trow1" width="30%"><strong>{$lang->jobliste_request_field_name}</strong><br /><span class="smalltext">{$lang->jobliste_request_field_name_desc}</span></td>
	<td class="trow1"><input type="text" name="name" class="textbox" maxlength="255" size="40" required="required" /></td>
</tr>
<tr>
	<td class="trow2"><strong>{$lang->jobliste_request_field_parent}</strong><br /><span class="smalltext">{$lang->jobliste_request_field_parent_desc}</span></td>
	<td class="trow2"><select name="parent_id">{$request_parent_options}</select></td>
</tr>
<tr>
	<td class="trow1" valign="top"><strong>{$lang->jobliste_request_field_note}</strong><br /><span class="smalltext">{$lang->jobliste_request_field_note_desc}</span></td>
	<td class="trow1"><textarea name="note" rows="4" cols="50"></textarea></td>
</tr>
</table>
<div class="jobliste_submit"><input type="submit" class="button" value="{$lang->jobliste_request_submit}" /></div>
</form>
