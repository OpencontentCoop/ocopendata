{def $charsets = fetch_charsets()}
{default attribute_base='ContentObjectAttribute'}
<select class="form-control" name="{$attribute_base}_ezstring_data_text_{$attribute.id}">
<option value=""></option>    
{foreach $charsets as $name}
<option value="{$name}"{if and( $attribute.has_content, $attribute.data_text|eq($name) )}selected="selected"{/if}>
    {$name}
</option>
{/foreach}
</select>
{/default}