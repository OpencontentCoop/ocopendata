<?php

use Opencontent\Opendata\Api\EnvironmentLoader;
use Opencontent\Opendata\Api\ContentBrowser;
use Opencontent\Opendata\Api\ContentRepository;
use Opencontent\Opendata\Api\ContentSearch;
use Opencontent\Opendata\Api\ClassRepository;
use Opencontent\Opendata\Api\TagRepository;

$Module = $Params['Module'];
$Environment = $Params['Environment'];
$Action = $Params['Action'];
$Param = isset( $_REQUEST['q'] ) ? urldecode($_REQUEST['q']) : $Params['Param'];

$Debug = isset( $_REQUEST['debug'] );

try
{
    $contentRepository = new ContentRepository();
    $contentBrowser = new ContentBrowser();
    $contentSearch = new ContentSearch();
    $classRepository = new ClassRepository();
    $tagsRepository = new TagRepository();

    if ( $Environment == 'classes' ){
        $data = (array) $classRepository->load($Action);
    }
    elseif ( $Environment == 'tags_tree' ){
        $data = (array) $tagsRepository->read($Action);
    }
    else
    {
        $currentEnvironment = EnvironmentLoader::loadPreset( $Environment );
        $contentRepository->setEnvironment( $currentEnvironment );
        $contentBrowser->setEnvironment( $currentEnvironment );
        $contentSearch->setEnvironment( $currentEnvironment );
        
        $parser = new ezpRestHttpRequestParser();
        $request = $parser->createRequest();
        $currentEnvironment->__set('request', $request);
    
        $data = array();
    
        if ( $Action == 'read' )
        {
            $data = (array)$contentRepository->read( $Param );
        }
        elseif ( $Action == 'search' )
        {
            $data = (array)$contentSearch->search( $Param );
        }
        elseif ( $Action == 'browse' )
        {
            $data = (array)$contentBrowser->browse( $Param );
        }
    }
}
catch( Exception $e )
{
    $data = array(
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage()
    );
    if ( $Debug )
    {
        $data['file'] = $e->getFile();
        $data['line'] = $e->getLine();
        $data['trace'] = $e->getTraceAsString();
    }
}
if ( $Debug )
{
    echo '<pre>';
    print_r( $data );
    echo '</pre>';
    eZDisplayDebug();
}
else
{
    header('Content-Type: application/json');
    echo json_encode( $data );
}

eZExecution::cleanExit();
