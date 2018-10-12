<div class="resource-container float-break">
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
    
    <h3 class="resource-label">Risorsa {$number}</h3>
    <div class="dataset-resource" style="margin: 10px 0;border: 1px solid #eee; padding: 20px;background: #eee">


        {foreach $other_attributes as $a}
            <div class="block">
            <label>{$a.contentclass_attribute.name|wash}</label>
            {if $a.contentclass_attribute.description} <p><em class="classattribute-description">{first_set( $a.contentclass_attribute.descriptionList[$content_language], $a.contentclass_attribute.description)|wash}</em></p>{/if}
            {attribute_edit_gui attribute_base=$attribute_base attribute=$a view_parameters=$view_parameters}
            </div>
        {/foreach}


        <div class="resource-tab">
            <p>
                <em><span class="classattribute-description">Utilizza solo una delle tre sorgenti</span></em>
            </p>
            <div class="block">
            <strong>{$url.contentclass_attribute.name|wash}</strong>
            <div id="coa-{$url.id}" class="resource-panel">
                <input class="box" type="text" size="70" name="{$attribute_base}_ezurl_url_{$url.id}" value="{$url.content|wash( xhtml )}" />
                <input type="hidden" name="{$attribute_base}_ezurl_text_{$url.id}" value="{$url.data_text|wash( xhtml )}" />
            </div>
            </div>

            <div class="block">
            <strong>{$file.contentclass_attribute.name|wash}</strong>
            <div id="coa-{$file.id}" class="resource-panel">
                {if $file.content}
                    {$file.content.original_filename|wash( xhtml )}
                    <input class="button" type="submit" name="CustomActionButton[{$file.id}_delete_binary]" value="{'Remove'|i18n( 'design/standard/content/datatype' )}" title="{'Remove the file from this draft.'|i18n( 'design/standard/content/datatype' )}" />
                {/if}
                <input type="hidden" name="MAX_FILE_SIZE" value="{$file.contentclass_attribute.data_int1}000000"/>
                <input class="inputfile" type="file" name="{$attribute_base}_data_binaryfilename_{$file.id}"  />
            </div>
            </div>

            <div class="block">
            <strong>{$api.contentclass_attribute.name|wash}</strong>
            <div id="coa-{$api.id}" class="resource-panel">
                <input class="box" type="text" size="70" name="{$attribute_base}_ezurl_url_{$api.id}" value="{$api.content|wash( xhtml )}" />
                <input type="hidden" name="{$attribute_base}_ezurl_text_{$api.id}" value="{$api.data_text|wash( xhtml )}" />
            </div>
            </div>
        </div>
    
    </div>
</div>