<tr>
	<td class="{$trow}"><strong>{$npc_name}</strong></td>
	<td class="{$trow} smalltext">{$npc_notes}</td>
	<td class="{$trow}">{$npc_jobs}<form action="jobliste.php#jobliste_form" method="get" class="jobliste_inline_form"><input type="hidden" name="entity" value="npc:{$npc_id}" /><input type="submit" class="button" value="{$lang->jobliste_npc_add_job}" /></form></td>
	<td class="{$trow} jobliste_actions"><form action="jobliste.php#jobliste_npc_form" method="get" class="jobliste_inline_form"><input type="hidden" name="action" value="npc-manage" /><input type="hidden" name="edit" value="{$npc_id}" /><input type="submit" class="button" value="{$lang->jobliste_edit}" /></form>{$jobliste_delete_form}</td>
</tr>
