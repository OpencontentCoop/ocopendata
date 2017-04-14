<?php
/** @var eZModule $module */
$module = $Params['Module'];
$tpl = eZTemplate::factory();
$tools = new OCOpenDataTools();

$action = $Params['Action'];
$parameter = $Params['Parameter'];

$error = null;
if ($action == 'add'){
    $generator = $tools->getDatasetGenerator();
    if ($generator instanceof OcOpendataDatasetGeneratorInterface) {
        try{
            $object = $generator->createFromClassIdentifier($parameter);
            $module->redirectTo('ckan/list');
        }catch(Exception $e){
            $error = $e->getMessage();
        }

    }
}

$tools->archiveDatasetIdList();

$nodeList = array();
$nodes = $tools->getDatasetNodes();
foreach($nodes as $node){
    $classIdentifier = str_replace('auto_dataset_', '', $node->attribute('remote_id'));
    $nodeList[$classIdentifier] = $node;
}
$tpl->setVariable('node_list', $nodeList);

//$aliasList = $tools->getAllArchivedDatasetIdList();
$aliasList = array($tools->getCurrentEndpointIdentifier() => $tools->getArchivedDatasetIdList($tools->getCurrentEndpointIdentifier()));
$tpl->setVariable('alias_list', $aliasList);

$groups = eZINI::instance('ocopendata_datasetgenerator.ini')->groups();
$classList = array_keys($groups);
$tpl->setVariable('class_list', $classList);

$tpl->setVariable('current_alias', $tools->getCurrentEndpointIdentifier());
$tpl->setVariable('error', $error);

echo $tpl->fetch( 'design:ckan/list.tpl' );
eZDisplayDebug();
eZExecution::cleanExit();

