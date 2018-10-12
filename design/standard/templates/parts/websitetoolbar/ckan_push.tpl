{if $content_object.class_identifier|eq('opendata_dataset')}
    <a href="{concat('ckan/push/', $content_object.id, '?format=html')|ezurl(no)}"
       title='Push to CKAN'>
        <img width="16" height="16" src={"ezwt-icon-ckan-push.png"|ezimage}/>
    </a>
{/if}