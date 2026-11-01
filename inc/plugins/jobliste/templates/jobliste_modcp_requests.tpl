<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->jobliste_modcp_title}</title>
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
	<td class="thead" colspan="5"><strong>{$lang->jobliste_modcp_pending_title}</strong></td>
</tr>
<tr>
	<td class="tcat smalltext" width="30%"><strong>{$lang->jobliste_request_col_category}</strong></td>
	<td class="tcat smalltext"><strong>{$lang->jobliste_request_col_note}</strong></td>
	<td class="tcat smalltext" width="15%"><strong>{$lang->jobliste_request_col_user}</strong></td>
	<td class="tcat smalltext" width="15%"><strong>{$lang->jobliste_request_col_date}</strong></td>
	<td class="tcat smalltext" width="10%" align="center"><strong>{$lang->jobliste_actions}</strong></td>
</tr>
{$jobliste_pending_rows}
</table>
<br />
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr>
	<td class="thead" colspan="5"><strong>{$lang->jobliste_modcp_handled_title}</strong></td>
</tr>
<tr>
	<td class="tcat smalltext" width="30%"><strong>{$lang->jobliste_request_col_category}</strong></td>
	<td class="tcat smalltext" width="12%"><strong>{$lang->jobliste_request_col_status}</strong></td>
	<td class="tcat smalltext"><strong>{$lang->jobliste_request_col_response}</strong></td>
	<td class="tcat smalltext" width="15%"><strong>{$lang->jobliste_request_col_user}</strong></td>
	<td class="tcat smalltext" width="18%"><strong>{$lang->jobliste_modcp_col_handled}</strong></td>
</tr>
{$jobliste_handled_rows}
</table>
</td>
</tr>
</table>
{$footer}
</body>
</html>
