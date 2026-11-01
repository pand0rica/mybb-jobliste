<tr>
	<td class="{$trow}"><strong>{$request_path}</strong></td>
	<td class="{$trow} smalltext">{$request_note}</td>
	<td class="{$trow}">{$request_user}</td>
	<td class="{$trow} smalltext">{$request_date}</td>
	<td class="{$trow}" align="center"><form action="modcp.php" method="get" class="jobliste_inline_form"><input type="hidden" name="action" value="jobliste_requests" /><input type="hidden" name="do" value="review" /><input type="hidden" name="id" value="{$request_id}" /><input type="submit" class="button" value="{$lang->jobliste_modcp_review}" /></form></td>
</tr>
