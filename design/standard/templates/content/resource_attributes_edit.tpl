    {run-once}
{ezscript_require(array( 'ezjsc::jquery', 'ezjsc::jqueryUI' ) )}
{literal}
<script type="text/javascript">
$(function() {
    $( ".resource-tab" ).each(function(){
        var activePanel = $("li", $(this)).index( $("li.has_content", $(this)) );
        if (activePanel > 0) {
            $(this).tabs({ selected:activePanel });
        }else{
            $(this).tabs();
        }
    });
});

</script>

<style>
    .resource-container{
        padding: 20px 0 20px 90px;
        border-bottom: 1px solid #ccc;
    }    
    .resource-label{
        margin: 44px 0 10px -90px;
        float: left;
    }
    .dataset-resource{
        float: left;
        width: 100%;
    }
    .dataset-resource li{
        background: none;
        display: inline;        
        margin: 0 5px 0 0;
        padding: 0;
    }    
    .dataset-resource ul{
        margin: 0;
    }
    .dataset-resource li.ui-state-active a{
        background: #eee;
        border: 1px solid #CCCCCC;
        border-bottom: none;
        font-weight: bold;
    }
    .dataset-resource li a{
        padding: 5px;
        display: inline-block;
        color:#000;
    }
    .resource-panel{
        border: 1px solid #CCCCCC;
        margin-bottom: 10px;
        padding: 5px;
        font-size: 16px;
    }
    .inputfile{
        padding: 10px 0;
    }
    .resource-panel input{
        border-color: #eaeaea;
    }
    .ui-tabs-hide{
        display: none;
    }
    .classattribute-description,
    li.tab-help a{
        text-decoration: none;
        color: #666 !important;
        background: #fff !important;
        cursor: help;
    }
</style>
{/literal}
{/run-once}

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
    
    <legend class="resource-label">Risorsa {$number}</legend>
    <div class="dataset-resource">
    
        
        <div class="resource-tab">
        
        <ul>
            <li class="{if $url.has_content} has_content{/if}"><a href="#coa-{$url.id}">{$url.contentclass_attribute.name|wash}</a></li>
            <li class="{if $file.has_content} has_content{/if}"><a href="#coa-{$file.id}">{$file.contentclass_attribute.name|wash}</a></li>
            <li class="{if $api.has_content} has_content{/if}"><a href="#coa-{$api.id}">{$api.contentclass_attribute.name|wash}</a></li>
            <li class="tab-help"><a href="#"><em><span class="classattribute-description">Utilizza solo uno dei tre tab disponibili</span></em></a></li>
        </ul>
        
        <div id="coa-{$url.id}" class="resource-panel">
            <input class="box" type="text" size="70" name="{$attribute_base}_ezurl_url_{$url.id}" value="{$url.content|wash( xhtml )}" />
            <input type="hidden" name="{$attribute_base}_ezurl_text_{$url.id}" value="{$url.data_text|wash( xhtml )}" />
        </div>
        
        <div id="coa-{$file.id}" class="resource-panel">
            {if $file.content}
                {$file.content.original_filename|wash( xhtml )}
                <input class="button" type="submit" name="CustomActionButton[{$file.id}_delete_binary]" value="{'Remove'|i18n( 'design/standard/content/datatype' )}" title="{'Remove the file from this draft.'|i18n( 'design/standard/content/datatype' )}" />
            {/if}
            <input type="hidden" name="MAX_FILE_SIZE" value="{$file.contentclass_attribute.data_int1}000000"/>
            <input class="inputfile" type="file" name="{$attribute_base}_data_binaryfilename_{$file.id}"  />            
        </div>
        
        <div id="coa-{$api.id}" class="resource-panel">     
            <input class="box" type="text" size="70" name="{$attribute_base}_ezurl_url_{$api.id}" value="{$api.content|wash( xhtml )}" />
            <input type="hidden" name="{$attribute_base}_ezurl_text_{$api.id}" value="{$api.data_text|wash( xhtml )}" />
        </div>
        
        
        </div>              
    
        {foreach $other_attributes as $a}
            <label>{$a.contentclass_attribute.name|wash}</label>
             {if $a.contentclass_attribute.description} <p><em class="classattribute-description">{first_set( $a.contentclass_attribute.descriptionList[$content_language], $a.contentclass_attribute.description)|wash}</em></p>{/if}
            {attribute_edit_gui attribute_base=$attribute_base attribute=$a view_parameters=$view_parameters}
        {/foreach}
        
    
    </div>
</div>