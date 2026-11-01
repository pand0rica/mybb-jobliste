<form action="jobliste.php" method="get" class="jobliste_inline_form">
<input type="hidden" name="action" value="edit" />
<input type="hidden" name="id" value="{$job_id}" />
<input type="hidden" name="return" value="{$jobliste_return}" />
<input type="submit" class="button" value="{$lang->jobliste_edit}" />
</form>{$jobliste_delete_form}