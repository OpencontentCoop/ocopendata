{def $_redirect = false()}
{if ezhttp_hasvariable( 'LastAccessesURI', 'session' )}
    {set $_redirect = ezhttp( 'LastAccessesURI', 'session' )}
{elseif $object.main_node_id}
    {set $_redirect = concat( 'content/view/full/', $object.main_node_id )}
{elseif ezhttp( 'url', 'get', true() )}
    {set $_redirect = ezhttp( 'url', 'get' )|wash()}
{/if}  

<form enctype="multipart/form-data" method="post" action={concat("/content/edit/",$object.id,"/",$edit_version,"/",$edit_language|not|choose(concat($edit_language,"/"),''))|ezurl}>
{include uri='design:parts/website_toolbar_edit.tpl'}

    <div class="maincontentheader">
        <h1>{"Edit %1 - %2"|i18n("design/standard/content/edit",,array($class.name|wash,$object.name|wash))}</h1>
    </div>
    
    <p class="text-right">
    {def $language_index = 0
         $from_language_index = 0
         $translation_list = $content_version.translation_list}

    {foreach $translation_list as $index => $translation}
       {if eq( $edit_language, $translation.language_code )}
          {set $language_index = $index}
       {/if}
    {/foreach}

    {if $is_translating_content}

        {def $from_language_object = $object.languages[$from_language]}

        {'Translating content from %from_lang to %to_lang'|i18n( 'design/ezwebin/content/edit',, hash(
            '%from_lang', concat( $from_language_object.name, '&nbsp;<img src="', $from_language_object.locale|flag_icon, '" style="vertical-align: middle;" alt="', $from_language_object.locale, '" />' ),
            '%to_lang', concat( $translation_list[$language_index].locale.intl_language_name, '&nbsp;<img src="', $translation_list[$language_index].language_code|flag_icon, '" style="vertical-align: middle;" alt="', $translation_list[$language_index].language_code, '" />' ) ) )}

    {else}

        {'Content in %language'|i18n( 'design/ezwebin/content/edit',, hash( '%language', $translation_list[$language_index].locale.intl_language_name ))}&nbsp;<img src="{$translation_list[$language_index].language_code|flag_icon}" style="vertical-align: middle;" alt="{$translation_list[$language_index].language_code}" />

    {/if}
    </p>

    {include uri="design:content/edit_validation.tpl"}

    {def $resources_attributes = array()}
    
    {foreach $content_attributes_grouped_data_map as $attribute_group => $content_attributes_grouped}    
        {foreach $content_attributes_grouped as $attribute_identifier => $attribute}
            
            {if $attribute_identifier|begins_with( 'resource' )}
                {set $resources_attributes = $resources_attributes|append( $attribute )}
                {skip}
            {/if}
            
            {def $contentclass_attribute = $attribute.contentclass_attribute}
            
            <div class="block ezcca-edit-datatype-{$attribute.data_type_string} ezcca-edit-{$attribute_identifier}">
            
            {if and( eq( $attribute.can_translate, 0 ), ne( $object.initial_language_code, $attribute.language_code ) )}
                <label>{first_set( $contentclass_attribute.nameList[$content_language], $contentclass_attribute.name )|wash}
                    {if $attribute.can_translate|not} <span class="nontranslatable">({'not translatable'|i18n( 'design/admin/content/edit_attribute' )})</span>{/if}:
                </label>
                {if $contentclass_attribute.description} <p><em class="classattribute-description">{first_set( $contentclass_attribute.descriptionList[$content_language], $contentclass_attribute.description)|wash}</em></p>{/if}
                {if $is_translating_content}
                    <div class="original">
                    {attribute_view_gui attribute_base=$attribute_base attribute=$attribute view_parameters=$view_parameters}
                    <input type="hidden" name="ContentObjectAttribute_id[]" value="{$attribute.id}" />
                    </div>
                {else}
                    {attribute_view_gui attribute_base=$attribute_base attribute=$attribute view_parameters=$view_parameters}
                    <input type="hidden" name="ContentObjectAttribute_id[]" value="{$attribute.id}" />
                {/if}
            {else}
                {if $is_translating_content}
                    <label{if $attribute.has_validation_error} class="message-error"{/if}>{first_set( $contentclass_attribute.nameList[$content_language], $contentclass_attribute.name )|wash}
                        {if $attribute.is_required} <span class="required">({'required'|i18n( 'design/admin/content/edit_attribute' )})</span>{/if}
                        {if $attribute.is_information_collector} <span class="collector">({'information collector'|i18n( 'design/admin/content/edit_attribute' )})</span>{/if}:                        
                    </label>
                    {if $contentclass_attribute.description} <p><em class="classattribute-description">{first_set( $contentclass_attribute.descriptionList[$content_language], $contentclass_attribute.description)|wash}</em></p>{/if}
                    <div class="original">
                    {attribute_view_gui attribute_base=$attribute_base attribute=$from_content_attributes_grouped_data_map[$attribute_group][$attribute_identifier] view_parameters=$view_parameters}
                    </div>
                    <div class="translation">
                    {if $attribute.display_info.edit.grouped_input}
                        <fieldset>
                        {attribute_edit_gui attribute_base=$attribute_base attribute=$attribute view_parameters=$view_parameters}
                        <input type="hidden" name="ContentObjectAttribute_id[]" value="{$attribute.id}" />
                        </fieldset>
                    {else}
                        {attribute_edit_gui attribute_base=$attribute_base attribute=$attribute view_parameters=$view_parameters}
                        <input type="hidden" name="ContentObjectAttribute_id[]" value="{$attribute.id}" />
                    {/if}
                    </div>
                {else}
                    {if $attribute.display_info.edit.grouped_input}
                        <fieldset>
                        <legend{if $attribute.has_validation_error} class="message-error"{/if}>{first_set( $contentclass_attribute.nameList[$content_language], $contentclass_attribute.name )|wash}
                            {if $attribute.is_required} <span class="required">({'required'|i18n( 'design/admin/content/edit_attribute' )})</span>{/if}
                            {if $attribute.is_information_collector} <span class="collector">({'information collector'|i18n( 'design/admin/content/edit_attribute' )})</span>{/if}                            
                        </legend>
                        {if $contentclass_attribute.description} <p><em class="classattribute-description">{first_set( $contentclass_attribute.descriptionList[$content_language], $contentclass_attribute.description)|wash}</em></p>{/if}
                        {attribute_edit_gui attribute_base=$attribute_base attribute=$attribute view_parameters=$view_parameters}
                        <input type="hidden" name="ContentObjectAttribute_id[]" value="{$attribute.id}" />
                        </fieldset>
                    {else}
                        <legend{if $attribute.has_validation_error} class="message-error"{/if}>{first_set( $contentclass_attribute.nameList[$content_language], $contentclass_attribute.name )|wash}
                            {if $attribute.is_required} <span class="required">({'required'|i18n( 'design/admin/content/edit_attribute' )})</span>{/if}
                            {if $attribute.is_information_collector} <span class="collector">({'information collector'|i18n( 'design/admin/content/edit_attribute' )})</span>{/if}:                            
                        </legend>
                        {if $contentclass_attribute.description} <p><em class="classattribute-description">{first_set( $contentclass_attribute.descriptionList[$content_language], $contentclass_attribute.description)|wash}</em></p>{/if}
                        {attribute_edit_gui attribute_base=$attribute_base attribute=$attribute view_parameters=$view_parameters}
                        <input type="hidden" name="ContentObjectAttribute_id[]" value="{$attribute.id}" />
                    {/if}
                {/if}
            {/if}
            
            </div>
            {undef $contentclass_attribute}
        {/foreach}
    {/foreach}
    
    {include uri="design:content/resources_attributes_edit.tpl" attributes=$resources_attributes}
    

    <div class="buttonblock">
        <input class="defaultbutton" type="submit" name="PublishButton" value="{'Send for publishing'|i18n('design/standard/content/edit')}" />
        <input class="button" type="submit" name="StoreButton" value="{'Store draft'|i18n('design/standard/content/edit')}" />
        <input class="button" type="submit" name="DiscardButton" value="{'Discard'|i18n('design/standard/content/edit')}" />
        <input type="hidden" name="RedirectIfDiscarded" value="{$_redirect}" />
        <input type="hidden" name="RedirectURIAfterPublish" value="{$_redirect}" />
    </div>

</table>

</form>
{undef $_redirect}