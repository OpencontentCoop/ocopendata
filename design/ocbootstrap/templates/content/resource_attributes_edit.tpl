<div class="attribute-edit row edit-row">
    {def $other_attributes = array()
         $number = 0}
    {foreach $resource_attributes as $attribute}
        {def $identifier = $attribute.contentclass_attribute_identifier
             $parts = $identifier|explode( '_' )}
            {switch match=$parts[2]}
            {case match='url'}
                {def $url = $attribute}
            {/case}
            {case match='file'}
                {def $file = $attribute}
            {/case}
            {case match='api'}
                {def $api = $attribute}
            {/case}
            {case}
                {set $other_attributes = $other_attributes|append( $attribute )}
                {set $number = $parts[1]}
            {/case}
            {/switch}
            
        {undef $identifier $parts}
    {/foreach}

    <div class="col-md-3">Risorsa {$number}</div>
    <div class="col-md-9">
    
        
        <div class="resource-tab">

            {foreach $other_attributes as $a}
            <div class="row edit-row">
                <div class="col-md-3">{$a.contentclass_attribute.name|wash}</div>
                <div class="col-md-9">
                    {if $a.contentclass_attribute.description}
                        <p><em class="classattribute-description">{first_set( $a.contentclass_attribute.descriptionList[$content_language], $a.contentclass_attribute.description)|wash}</em></p>
                    {/if}
                    {attribute_edit_gui attribute_base=$attribute_base attribute=$a view_parameters=$view_parameters html_class='form-control'}
                </div>
            </div>
            {/foreach}

            <ul class="nav nav-pills">
                <li class="{if $url.has_content} has_content active{/if}"><a data-toggle="pill" href="#coa-{$url.id}">{$url.contentclass_attribute.name|wash}</a></li>
                <li class="{if $file.has_content} has_content active{/if}"><a data-toggle="pill" href="#coa-{$file.id}">{$file.contentclass_attribute.name|wash}</a></li>
                <li class="{if $api.has_content} has_content active{/if}"><a data-toggle="pill" href="#coa-{$api.id}">{$api.contentclass_attribute.name|wash}</a></li>
                <li class="tab-help"><a href="#"><em><span class="classattribute-description">Utilizza solo uno dei tre tab disponibili</span></em></a></li>
            </ul>

            <div class="tab-content">
                <div role="tabpanel" id="coa-{$url.id}" class="resource-panel tab-pane{if $url.has_content} has_content active{/if}">
                    <input class="form-control" type="text" size="70" name="{$attribute_base}_ezurl_url_{$url.id}" value="{$url.content|wash( xhtml )}" />
                    <input type="hidden" name="{$attribute_base}_ezurl_text_{$url.id}" value="{$url.data_text|wash( xhtml )}" />
                </div>

                <div role="tabpanel" id="coa-{$file.id}" class="resource-panel tab-pane{if $file.has_content} has_content active{/if}">
                    {if $file.content}
                        {$file.content.original_filename|wash( xhtml )}
                        <input class="button" type="submit" name="CustomActionButton[{$file.id}_delete_binary]" value="{'Remove'|i18n( 'design/standard/content/datatype' )}" title="{'Remove the file from this draft.'|i18n( 'design/standard/content/datatype' )}" />
                    {/if}
                    <input type="hidden" name="MAX_FILE_SIZE" value="{$file.contentclass_attribute.data_int1}000000"/>
                    <input class="inputfile" type="file" name="{$attribute_base}_data_binaryfilename_{$file.id}"  />
                </div>

                <div role="tabpanel" id="coa-{$api.id}" class="resource-panel tab-pane{if $api.has_content} has_content active{/if}">
                    <input class="form-control" type="text" size="70" name="{$attribute_base}_ezurl_url_{$api.id}" value="{$api.content|wash( xhtml )}" />
                    <input type="hidden" name="{$attribute_base}_ezurl_text_{$api.id}" value="{$api.data_text|wash( xhtml )}" />
                </div>
            </div>
        </div>
    
    </div>
</div>