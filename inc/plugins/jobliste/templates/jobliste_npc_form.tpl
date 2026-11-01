<form action="jobliste.php" method="post" id="jobliste_npc_form">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="npc-save" />
<input type="hidden" name="npc_id" value="{$npc_form_id}" />
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr>
	<td class="thead" colspan="2"><strong>{$npc_form_title}</strong></td>
</tr>
<tr>
	<td class="trow1" width="30%"><strong>{$lang->jobliste_npc_name}</strong></td>
	<td class="trow1"><input type="text" name="name" class="textbox" maxlength="255" required="required" value="{$npc_form_name}" /></td>
</tr>
<tr>
	<td class="trow2" valign="top"><strong>{$lang->jobliste_npc_notes}</strong><br /><span class="smalltext">{$lang->jobliste_npc_notes_desc}</span></td>
	<td class="trow2"><textarea name="notes" rows="3" cols="50">{$npc_form_notes}</textarea></td>
</tr>
</table>
<div class="jobliste_submit"><input type="submit" class="button" value="{$lang->jobliste_npc_save}" /></div>
</form>
