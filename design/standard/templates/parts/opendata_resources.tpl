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

    {def $style = 'col-even'
         $has_content = false()}

    {foreach $resource_attributes as $attribute}
        {if $attribute.has_content}
            {set $has_content = true()}
            {break}
        {/if}
    {/foreach}

    {if $has_content}
    <div class="opendata_resource oggetti-correlati">
        <div class="border-header border-box box-trans-blue box-allegati-header">
            <div class="border-tl"><div class="border-tr"><div class="border-tc"></div></div></div>
            <div class="border-ml"><div class="border-mr"><div class="border-mc">
            <div class="border-content">
                <h2>Risorsa {$counter}</h2>
            </div>
            </div></div></div>
        </div>
        <div class="border-body border-box box-violet box-allegati-content">
            <div class="border-ml"><div class="border-mr"><div class="border-mc">
            <div class="border-content">
            {foreach $resource_attributes as $attribute}
            {def $attribute_has_content = $attribute.has_content}
            {if $attribute.data_type_string|eq( 'ezurl' )}
                {set $attribute_has_content = $attribute.content|ne('')}
            {/if}
            {if $attribute_has_content}
                {if $style|eq('col-even')}{set $style='col-odd'}{else}{set $style='col-even'}{/if}
                <div class="{$style} col float-break">
                    <div class="col-title"><span class="label">{$attribute.contentclass_attribute_name}</span></div>
                    <div class="col-content"><div class="col-content-design">
                        {attribute_view_gui attribute=$attribute}
                    </div></div>
                </div>
            {/if}
            {undef $attribute_has_content}
            {/foreach}
            </div>
            </div></div></div>
            <div class="border-bl"><div class="border-br"><div class="border-bc"></div></div></div>
        </div>
    </div>
    {/if}


    {undef $resource_attributes $style $has_content}
{/for}