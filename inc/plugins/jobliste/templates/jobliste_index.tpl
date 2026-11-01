<div id="jobliste_form">
{$limit_note}
{$jobliste_form}
</div>
<br />
{$jobliste_request_section}
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr>
	<td class="thead" colspan="2"><strong>{$lang->jobliste_my_jobs}</strong></td>
</tr>
<tr>
	<td class="tcat smalltext" colspan="2"><a href="jobliste.php?action=overview" class="jobliste_overview_link">{$lang->jobliste_overview_link}</a>{$jobliste_npc_link}</td>
</tr>
{$jobliste_entity_blocks}
</table>
