<div class="content-view-full class-{$node.class_identifier} row">

    <div class="content-main wide">

        <h1>{$node.name|wash()}</h1>

        {foreach $node.object.contentobject_attributes as $attribute}
            {if $node|has_attribute( $attribute.contentclass_attribute_identifier )}
                {if $attribute.contentclass_attribute_identifier|begins_with( 'resource' )}
                    {skip}
                {/if}
                <dl class="dl-horizontal attribute-{$attribute.contentclass_attribute_identifier}">
                    <dt>{$attribute.contentclass_attribute_name}</dt>
                    <dd>
                        {attribute_view_gui attribute=$attribute}
                    </dd>
                </dl>
            {/if}
        {/foreach}

        {include uri=concat('design:parts/opendata_resources.tpl') node=$node}

    </div>

</div>
