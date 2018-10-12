{def $resources_attributes = array()}
{foreach $node.object.contentobject_attributes as $attribute}
    {if $attribute.contentclass_attribute_identifier|begins_with( 'resource' )}
        {set $resources_attributes = $resources_attributes|append($attribute)}
    {/if}
{/foreach}

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

    {def $has_content = false()}

    {foreach $resource_attributes as $attribute}
        {if $attribute.has_content}
            {set $has_content = true()}
            {break}
        {/if}
    {/foreach}

    {if $has_content}
        <div class="opendata_resource widget">
            <h2>Risorsa {$counter}</h2>
            <div class="widget_content">
                {foreach $resource_attributes as $attribute}
                    {def $attribute_has_content = $attribute.has_content}
                    {if $attribute.data_type_string|eq( 'ezurl' )}
                        {set $attribute_has_content = $attribute.content|ne('')}
                    {/if}
                    {if $attribute_has_content}
                        <dl class="dl-horizontal">
                            <dt>{$attribute.contentclass_attribute_name}</dt>
                            <dd>
                                {attribute_view_gui attribute=$attribute}
                            </dd>
                        </dl>
                    {/if}
                    {undef $attribute_has_content}
                {/foreach}
            </div>
        </div>
    {/if}


    {undef $resource_attributes $has_content}
{/for}