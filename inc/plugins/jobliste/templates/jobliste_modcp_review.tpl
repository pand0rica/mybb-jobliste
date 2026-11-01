<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->jobliste_modcp_review_title}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$modcp_nav}
<td valign="top">
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr>
	<td class="thead" colspan="2"><strong>{$lang->jobliste_modcp_review_title}</strong></td>
</tr>
<tr>
	<td class="trow1" width="30%"><strong>{$lang->jobliste_request_col_category}</strong></td>
	<td class="trow1">{$request_path}</td>
</tr>
<tr>
	<td class="trow2" valign="top"><strong>{$lang->jobliste_request_col_note}</strong></td>
	<td class="trow2">{$request_note}</td>
</tr>
<tr>
	<td class="trow1"><strong>{$lang->jobliste_request_col_user}</strong></td>
	<td class="trow1">{$request_user} <span class="smalltext">({$request_date})</span></td>
</tr>
</table>
<br />
<form action="modcp.php" method="post">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="jobliste_requests" />
<input type="hidden" name="sub_action" value="approve" />
<input type="hidden" name="id" value="{$request_id}" />
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr>
	<td class="thead" colspan="2"><strong>{$lang->jobliste_modcp_approve_title}</strong><div class="smalltext">{$lang->jobliste_modcp_approve_desc}</div></td>
</tr>
<tr>
	<td class="trow1" width="30%"><strong>{$lang->jobliste_request_field_name}</strong></td>
	<td class="trow1"><input type="text" name="name" class="textbox" maxlength="255" size="40" required="required" value="{$review_name}" /></td>
</tr>
<tr>
	<td class="trow2"><strong>{$lang->jobliste_request_field_parent}</strong></td>
	<td class="trow2"><select name="parent_id">{$review_parent_options}</select></td>
</tr>
<tr>
	<td class="trow1"><strong>{$lang->jobliste_modcp_field_custom}</strong><br /><span class="smalltext">{$lang->jobliste_modcp_field_custom_desc}</span></td>
	<td class="trow1"><label><input type="checkbox" class="checkbox" name="allow_custom_text" value="1" /> {$lang->jobliste_modcp_field_custom_label}</label></td>
</tr>
<tr>
	<td class="trow2" valign="top"><strong>{$lang->jobliste_modcp_field_response}</strong><br /><span class="smalltext">{$lang->jobliste_modcp_field_response_approve_desc} {$review_pm_note}</span></td>
	<td class="trow2"><textarea name="response" rows="3" cols="50"></textarea></td>
</tr>
</table>
<div align="center"><br /><input type="submit" class="button" value="{$lang->jobliste_modcp_approve_submit}" /></div>
</form>
<br />
<form action="modcp.php" method="post">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="jobliste_requests" />
<input type="hidden" name="sub_action" value="reject" />
<input type="hidden" name="id" value="{$request_id}" />
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr>
	<td class="thead" colspan="2"><strong>{$lang->jobliste_modcp_reject_title}</strong></td>
</tr>
<tr>
	<td class="trow1" width="30%" valign="top"><strong>{$lang->jobliste_modcp_field_response}</strong><br /><span class="smalltext">{$lang->jobliste_modcp_field_response_reject_desc} {$review_pm_note}</span></td>
	<td class="trow1"><textarea name="response" rows="3" cols="50"></textarea></td>
</tr>
</table>
<div align="center"><br /><input type="submit" class="button" value="{$lang->jobliste_modcp_reject_submit}" /></div>
</form>
</td>
</tr>
</table>
{$footer}
</body>
</html>
