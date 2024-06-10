<?php

use Opencontent\Opendata\Api\EnvironmentLoader;
use Opencontent\Opendata\Api\ContentRepository;
use Opencontent\Opendata\Api\ContentSearch;
use Opencontent\Opendata\Api\ClassRepository;
use Opencontent\Opendata\Api\TagRepository;

class OCOpenDataOperators
{
    function operatorList()
    {
        return array(
            'fetch_licenses',
            'fetch_charsets',
            'api_search',
            'api_read',
            'api_class',
            'api_tagtree',
        );
    }

    function namedParameterPerOperator()
    {
        return true;
    }

    function namedParameterList()
    {
        return array(
            'fetch_licenses' => array(),
            'fetch_charsets' => array(),
            'api_search' => array(
                'query' => array( 'type' => 'string', 'required' => true, 'default' => false ),
                'environment' => array( 'type' => 'string', 'required' => false, 'default' => 'content' ),
                'return_all' => array( 'type' => 'boolean', 'required' => false, 'default' => false ),
            ),
            'api_read' => array(
                'query' => array( 'type' => 'string', 'required' => true, 'default' => false ),
                'environment' => array( 'type' => 'string', 'required' => false, 'default' => 'content' )
            ),
            'api_class' => array(
                'identifier' => array( 'type' => 'string', 'required' => true, 'default' => false )
            ),
            'api_tagtree' => array(
                'identifier' => array( 'type' => 'string', 'required' => true, 'default' => false ),
                'offset' => array( 'type' => 'integer', 'required' => false, 'default' => 0 ),
                'limit' => array( 'type' => 'integer', 'required' => false, 'default' => 100 ),
                'main_translation' => array( 'type' => 'boolean', 'required' => false, 'default' => true ),
            )
        );
    }

    function modify( &$tpl, &$operatorName, &$operatorParameters, &$rootNamespace, &$currentNamespace, &$operatorValue, &$namedParameters )
    {

        switch ($operatorName)
        {
            case 'api_tagtree':
            {
                $tagsRepository = new TagRepository();
                $identifier = $namedParameters['identifier'];
                try {
                    $data = (array)$tagsRepository->read(
                        $identifier,
                        (int)$namedParameters['offset'],
                        (int)$namedParameters['limit'],
                        $namedParameters['main_translation']
                        )->jsonSerialize();
                }
                catch( Exception $e )
                {
                    $data = array();
                }
                $operatorValue = $data;

            } break;

            case 'api_class':
            {
                $identifier = $namedParameters['identifier'];
                $classRepository = new ClassRepository();
                try
                {
                    if (is_array($identifier)){
                        $data = array();
                        foreach($identifier as $id){
                            $data[] = (array)$classRepository->load($id);
                        }
                    }else {
                        $data = (array)$classRepository->load($identifier);
                    }
                }
                catch( Exception $e )
                {
                    $data = array(
                        'error_code' => $e->getCode(),
                        'error_message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    );
                }
                $operatorValue = $data;
            } break;

            case 'api_search':
            case 'api_read':
            {
                $Environment = $namedParameters['environment'];
                $Action = $operatorName == 'api_search' ? 'search' : 'read';
                $Param = $namedParameters['query'];

                try
                {
                    $contentRepository = new ContentRepository();
                    $contentSearch = new ContentSearch();

                    $currentEnvironment = EnvironmentLoader::loadPreset( $Environment );
                    $contentRepository->setEnvironment( $currentEnvironment );
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
                        if ($namedParameters['return_all'] && $data['nextPageQuery']) {
                            $nextPageQuery =  $data['nextPageQuery'];
                            while($nextPageQuery) {
                                $nextData = (array)$contentSearch->search($nextPageQuery);
                                $data['searchHits'] = array_merge($data['searchHits'], $nextData['searchHits']);
                                $nextPageQuery = $nextData['nextPageQuery'];
                            }
                        }
                    }
                }
                catch( Exception $e )
                {
                    $data = array(
                        'error_code' => $e->getCode(),
                        'error_message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    );
                }
                $operatorValue = $data;
            } break;

            case 'fetch_charsets':
                $returnArray = mb_list_encodings();
                $operatorValue = $returnArray;
                break;

            case 'fetch_licenses':
                $openDataTools = new OCOpenDataTools();
                $licenses = $openDataTools->getLicenseList();
                $returnArray = array();
                foreach( $licenses as $license )
                {
                    $returnArray[$license->id] = $license->title;
                }
                $operatorValue = $returnArray;
                break;
        }
    }

}
