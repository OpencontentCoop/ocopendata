<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Ckan Dataset Sync</title>

    <link href="//getbootstrap.com/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="//code.jquery.com/jquery-1.12.0.min.js" type="application/javascript"></script>
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">

    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
    <script src="//getbootstrap.com/dist/js/bootstrap.min.js"></script>

</head>

<body>

<div class="container">

<h1>Ckan Dataset Sync</h1>

{if $error}
    <div class="alert alert-danger">
        <h2>{$error}</h2>
    </div>
{/if}

<table class="table table-striped">
    <tr>
        <th></th>
        {foreach $alias_list as $alias => $aliasData}
            <th{if $current_alias|eq($alias)} class="warning" {/if}>
                {$alias|wash()}
            </th>
        {/foreach}
    </tr>
    <tr>
        <th colspan="{count($alias_list)|inc()}">Organization</th>
    </tr>
    <tr>
        <td>{ezini('SiteSettings', 'SiteName')|wash()}</td>
        {foreach $alias_list as $alias => $aliasData}
            <td{if $current_alias|eq($alias)} class="warning" {/if}>
                <table>
                    <tr>
                        <td><a href="{'ckan/show/org?format=html'|ezurl(no)}" class="btn btn-xs btn-{if is_set($aliasData['org'])}info{else}disabled{/if}">
                           SHOW
                        </a></td>
                        <td><a data-action="push" data-alias="{$alias}" data-id="org" class="btn btn-xs btn-success">PUSH</a></td>
                        <!--<td><a data-action="delete" data-alias="{$alias}" data-id="org" class="btn btn-xs btn-warning">DELETE</a></td>-->
                        <td><a data-action="purge" data-alias="{$alias}" data-id="org" class="btn btn-xs btn-danger">PURGE</a></td>
                    </tr>
                </table>
            </td>
        {/foreach}
    </tr>
    <tr>
        <th colspan="{count($alias_list)|inc()}">Dataset</th>
    </tr>
    {foreach $class_list as $class}
        {if is_set($node_list[$class])}
            {def $node = $node_list[$class]}

        <tr>
            <td>
                <a href="{$node.url|ezurl(no)}">
                    {$node.name|wash()}
                    <br />
                    <small>{$node.contentobject_id} - {$node.remote_id|wash()}</small>
                </a>
            </td>
            {foreach $alias_list as $alias => $aliasData}
                <td{if $current_alias|eq($alias)} class="warning" {/if}>
                    <table>
                        {*if is_set($aliasData[$node.contentobject_id])}
                            <tr class="dataset_id"><td colspan="4"><p><span class="label label-default">{$aliasData[$node.contentobject_id]}</span></p></td></tr>
                        {/if*}
                        <tr>
                            <td>
                                <a data-toggle="modal" data-target=".modal" href="{concat('ckan/show/',$node.contentobject_id,'?format=html')|ezurl(no)}" class="showButton btn btn-xs {if is_set($aliasData[$node.contentobject_id])}btn-info{else}btn-disabled{/if}">
                                    SHOW
                                </a>
                            </td>
                            <td><a data-action="push" data-alias="{$alias}" data-id="{$node.contentobject_id}" class="btn btn-xs btn-success">PUSH</a></td>
                            <!--<td><a data-action="delete" data-alias="{$alias}" data-id="{$node.contentobject_id}" class="btn btn-xs btn-warning">DELETE</a></td>-->
                            <td><a data-action="purge" data-alias="{$alias}" data-id="{$node.contentobject_id}" class="btn btn-xs btn-danger">PURGE</a></td>
                        </tr>
                    </table>
                </td>
            {/foreach}
        </tr>
        {undef $node}
        {else}
            <tr>
                <td><a class="btn btn-danger" href="{concat('ckan/list/add/', $class)|ezurl(no)}">Genera dataset per la classe {$class}</a></td>
                {foreach $alias_list as $alias => $aliasData}
                    <td></td>
                {/foreach}
            </tr>
        {/if}
    {/foreach}
</table>

{literal}
<script>
    $(document).ready(function(){
        var spinner = $('<i class="fa fa-circle-o-notch fa-spin"></i>');
        $('[data-action]').on('click',function(e){
            $(this).append(spinner);
            var show = $(this).parents('tr').find('a.showButton');
            var action = $(this).data('action');
            var id = $(this).data('id');
            $.get('/ckan/'+action+'/'+id, function(data){
                if (data.result == 'error'){
                    alert(data.error);
                }else {
//                    console.log(show);
                    if (action == 'push') {
                        show.removeClass('btn-disabled').addClass('btn-info');
                    }
                    if (action == 'purge') {
                        show.removeClass('btn-info').addClass('btn-disabled');
                    }
                }
                spinner.remove();
            });
        });
    });
</script>
{/literal}

</div>

<div class="modal fade" tabindex="-1" role="dialog" aria-labelledby="ModalLabel">
<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        ...
    </div>
</div>
</div>

</body>
</html>
