<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr>
	<td class="thead"><strong>{$lang->jobliste_overview_title}</strong></td>
</tr>
<tr>
	<td class="trow1">
		<div class="jobliste_overview_tools" data-jobliste-tools="1" hidden="hidden">
			<input type="search" class="textbox jobliste_filter" placeholder="{$lang->jobliste_overview_search}" aria-label="{$lang->jobliste_overview_search}" autocomplete="off" data-jobliste-filter="jobliste_overview_tree" />
			<button type="button" class="button" data-jobliste-expand="1">{$lang->jobliste_expand_all}</button>
			<button type="button" class="button" data-jobliste-expand="0">{$lang->jobliste_collapse_all}</button>
		</div>
		<p class="smalltext jobliste_overview_count">{$jobliste_overview_count}</p>
		<div class="jobliste_tabs" role="tablist" aria-label="{$lang->jobliste_overview_title}" data-jobliste-tabs="jobliste_overview_tree" hidden="hidden">{$jobliste_tab_buttons}</div>
		<div id="jobliste_overview_tree" class="jobliste_tree">
			{$jobliste_tree}
		</div>
		<p class="smalltext jobliste_filter_empty" hidden="hidden">{$lang->jobliste_filter_no_results}</p>
	</td>
</tr>
</table>
<script src="inc/plugins/jobliste/assets/jobliste.js?v={$jobliste_js_version}"></script>
