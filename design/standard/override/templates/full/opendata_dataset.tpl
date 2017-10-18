{ezpagedata_set( 'extra_menu', false() )}
<div class="border-box">
<div class="border-content">

 <div class="global-view-full content-view-full">
  <div class="class-{$node.object.class_identifier}">

	<h1>{$node.name|wash()}</h1>

    {* DATA e ULTIMAMODIFICA *}
    {include name = last_modified
      node = $node
      view_parameters = $view_parameters
      uri = 'design:parts/openpa/last_modified.tpl'}

    {* EDITOR TOOLS *}
    {include name = editor_tools
      node = $node
      uri = 'design:parts/openpa/editor_tools.tpl'}


      {* ATTRIBUTI BASE: mostra i contenuti del nodo *}
    {include name = attributi_base
             uri = 'design:parts/opendata_detail.tpl'
             node = $node}
    
    {* ATTRIBUTI BASE: mostra i contenuti del nodo *}
    {include name = attributi_base
             uri = 'design:parts/opendata_resources.tpl'
             node = $node}
    
    </div>
</div>

</div>
</div>
