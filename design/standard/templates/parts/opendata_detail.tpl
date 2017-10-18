{def $attributi_da_escludere = openpaini( 'GestioneAttributi', 'attributi_da_escludere' )
     $oggetti_senza_label = openpaini( 'GestioneAttributi', 'oggetti_senza_label' )
     $attributi_senza_link = openpaini( 'GestioneAttributi', 'attributi_senza_link' )
     $attributi_da_evidenziare = openpaini( 'GestioneAttributi', 'attributi_da_evidenziare' )}

<div class="attributi-base">
	{def $style='col-odd'}
   	{foreach $node.object.contentobject_attributes as $attribute}

        {if $attribute.contentclass_attribute_identifier|begins_with( 'resource' )}
            {skip}
        {/if}

        {if and( $attribute.has_content, $attribute.content|ne('0') )}

        	{if $attributi_da_escludere|contains( $attribute.contentclass_attribute_identifier )|not()}

                {if $style|eq( 'col-even' )}{set $style = 'col-odd'}{else}{set $style = 'col-even'}{/if}

                {if $oggetti_senza_label|contains( $attribute.contentclass_attribute_identifier )|not()}
				   <div class="{$style} col float-break attribute-{$attribute.contentclass_attribute_identifier}">
						<div class="col-title"><span class="label">{$attribute.contentclass_attribute_name}</span></div>
						<div class="col-content"><div class="col-content-design">
							{if $attributi_senza_link|contains( $attribute.contentclass_attribute_identifier )}
								{attribute_view_gui href='nolink' attribute=$attribute}
							{else}
								{attribute_view_gui attribute=$attribute}
							{/if}
						</div></div>
				   </div>
				{else}
				   <div class="{$style} col col-notitle float-break attribute-{$attribute.contentclass_attribute_identifier}">
					<div class="col-content"><div class="col-content-design">
						{if $attributi_senza_link|contains( $attribute.contentclass_attribute_identifier )}
							{attribute_view_gui href='nolink' attribute=$attribute show_flip=true()}
						{else}
							{attribute_view_gui attribute=$attribute show_flip=true()}
						{/if}
					</div></div>
				   </div>
				{/if}
			{/if}
		{/if}
	{/foreach}
</div>