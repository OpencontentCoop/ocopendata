{def $licenses = fetch_licenses()}
{default attribute_base='ContentObjectAttribute'}
{foreach $licenses as $id => $name}
<input type="radio" name="{$attribute_base}_ezstring_data_text_{$attribute.id}" value="{$id}" {if $attribute.data_text|eq($id)}checked="checked"{/if}/> {$name}
{delimiter}<br />{/delimiter}
{/foreach}
{/default}