{def $count_resources = array()}
{foreach $resources_attributes as $attribute}
    {def $name_parts = $attribute.contentclass_attribute_identifier|explode( '_' )}
        {if $count_resources|contains( $name_parts[1] )|not()}
            {set $count_resources = $count_resources|append( $name_parts[1] )}
        {/if}
    {undef $name_parts}
{/foreach}


{for 1 to count($count_resources) as $counter}
    {def $resource_attributes = array()}
    {foreach $resources_attributes as $attribute}
        {def $name_parts = $attribute.contentclass_attribute_identifier|explode( '_' )}
            {if $name_parts[1]|eq( $counter )}
                {set $resource_attributes = $resource_attributes|append( $attribute )}
            {/if}
        {undef $name_parts}
    {/foreach}
    {include uri="design:content/resource_attributes_edit.tpl" attributes=$resource_attributes}
    {undef $resource_attributes}
{/for}
