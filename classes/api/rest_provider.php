<?php

use Opencontent\Opendata\Api\EnvironmentLoader;

class OCOpenDataProvider extends ezpRestApiProvider
{

    public function getRoutes()
    {
        return array_merge(
            $this->getExtraRoutes(),
            $this->getVersion1Routes(),
            $this->getVersion2Routes()
        );
    }

    public function getVersion2Routes()
    {
        $routes = array(
            'openData2class' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/classes/:Identifier',
                    'OCOpenDataController2',
                    'classRead',
                    array(),
                    'http-get',
                    null,
                    'Read content class definition'
                ), 2
            ),
            'openData2classes' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/classes',
                    'OCOpenDataController2',
                    'classListRead',
                    array(),
                    'http-get',
                    null,
                    'Read the list of content classes'
                ), 2
            ),
            'openData2create' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/:EnvironmentSettings/create',
                    'OCOpenDataController2',
                    'contentCreate',
                    array(
                        'EnvironmentSettings' => 'content'
                    ),
                    'http-post',
                    null,
                    'Create a content'
                ), 2
            ),
            'openData2update' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/:EnvironmentSettings/update',
                    'OCOpenDataController2',
                    'contentUpdate',
                    array(
                        'EnvironmentSettings' => 'content'
                    ),
                    'http-post',
                    null,
                    'Update a content'
                ), 2
            ),
//            'openData2delete' => new OcOpenDataVersionedRoute(
//                new OcOpenDataRoute(
//                    '/:EnvironmentSettings/delete',
//                    'OCOpenDataController2',
//                    'contentDelete',
//                    array(
//                        'EnvironmentSettings' => 'content'
//                    ),
//                    'http-post',
//                    null,
//                    'Delete a content'
//                ), 2
//            ),
            'openData2download' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/download/:ObjectId/:Id/:Version/:Filename',
                    'OCOpenDataController2',
                    'contentDownload',
                    array(),
                    'http-post',
                    null,
                    'Download a binary attachment'
                ), 2
            )
        );

        foreach (EnvironmentLoader::getAvailablePresetIdentifiers() as $identifier) {
            $envSuffix = $identifier == 'content' ? '' : " in $identifier context";
            $routes["openData2{$identifier}Read"] = new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    "/{$identifier}/read/:ContentObjectIdentifier",
                    'OCOpenDataController2',
                    EnvironmentLoader::needAccess($identifier) ? 'protectedRead' : 'anonymousRead',
                    array(
                        'EnvironmentSettings' => $identifier
                    ),
                    'http-get',
                    null,
                    "Read a content$envSuffix"
                ), 2
            );
            $routes["openData2{$identifier}SearchGetQuery"] = new ezpRestVersionedRoute(
                new OcOpenDataRoute(
                    "/{$identifier}/search",
                    'OCOpenDataController2',
                    EnvironmentLoader::needAccess($identifier) ? 'protectedSearch' : 'anonymousSearch',
                    array(
                        'EnvironmentSettings' => $identifier,
                        'Query' => null
                    ),
                    'http-get',
                    null,
                    "Search contents$envSuffix"
                ), 2
            );
            $routes["openData2{$identifier}Search"] = new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    "/{$identifier}/search/:Query",
                    'OCOpenDataController2',
                    EnvironmentLoader::needAccess($identifier) ? 'protectedSearch' : 'anonymousSearch',
                    array(
                        'EnvironmentSettings' => $identifier
                    ),
                    'http-get',
                    null,
                    "Search contents$envSuffix"
                ), 2
            );
            if (in_array($identifier, array('content', 'full'))) {
                $routes["openData2{$identifier}Browse"] = new OcOpenDataVersionedRoute(
                    new OcOpenDataRegexpRoute(
                        '@^/' . $identifier . '/browse/(?P<ContentNodeIdentifier>\w+)@',
                        'OCOpenDataController2',
                        EnvironmentLoader::needAccess($identifier) ? 'protectedBrowse' : 'anonymousBrowse',
                        array(
                            'EnvironmentSettings' => $identifier
                        ),
                        null,
                        "Browse content tree$envSuffix"
                    ), 2
                );
            }
        }

        return $routes;
    }

    public function getExtraRoutes()
    {
        $routes = array(

            'openDataHelp' => new OcOpenDataRoute('/', 'OCOpenDataController', 'help'),

            'openData1Help' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute('/', 'OCOpenDataController', 'help', array(), 'http-get', null, 'Api version 1 endpoint'), 1
            ),
            'openDataHelpList' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute('/help', 'OCOpenDataController', 'helpList', array(), 'http-get', null, 'Api version 1 help endpoint'), 1
            ),

            'openData2Help' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute('/', 'OCOpenDataController', 'help', array(), 'http-get', null, 'Api version 2 endpoint'), 2
            ),

            'openData2tags' => new OcOpenDataVersionedRoute(
                new OcOpenDataRegexpRoute(
                    '@^/tags_tree(?P<Tag>.+)@',
                    'OCOpenDataTagController',
                    'tagsTree',
                    array(
                        'offset' => 0,
                        'limit' => 100
                    ),
                    'tags_tree/:Tag',
                    'Browse tag tree'
                ), 2
            ),
        );

        return $routes;
    }

    public function getVersion1Routes()
    {
        $routes = array(
            'ezpListAtom' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/content/node/:nodeId/listAtom',
                    'ezpRestAtomController',
                    'collection',
                    array(),
                    null,
                    null,
                    'Get a content node in atom format'
                ), 1
            ),
            // @TODO : Make possible to interchange optional params positions
            'ezpList' => new OcOpenDataVersionedRoute(
                new OcOpenDataRegexpRoute(
                    '@^/content/node/(?P<nodeId>\d+)/list(?:/offset/(?P<offset>\d+))?(?:/limit/(?P<limit>\d+))?(?:/sort/(?P<sortKey>\w+)(?:/(?P<sortType>asc|desc))?)?$@',
                    'OCOpenDataController',
                    'list',
                    array(
                        'offset' => 0,
                        'limit' => 10
                    ),
                    'content/node/:nodeId/list/offset/:offset/limit/:limit/sort/:sortKey/:sortType',
                    'Get the node children list by nodeId'
                ), 1
            ),
            'ezpNode' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/content/node/:nodeId', 'OCOpenDataController', 'viewContent', array(), null, '', 'Get content node by id'
                ), 1
            ),
            'ezpFieldsByNode' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/content/node/:nodeId/fields',
                    'OCOpenDataController',
                    'viewFields',
                    array(), null, '', 'Get content fields by node id'
                ), 1
            ),
            'ezpFieldByNode' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/content/node/:nodeId/field/:fieldIdentifier',
                    'OCOpenDataController',
                    'viewField',
                    array(), null, '', 'Get content field by node id and field identifier'
                ), 1
            ),
            'ezpChildrenCount' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/content/node/:nodeId/childrenCount',
                    'OCOpenDataController',
                    'countChildren',
                    array(), null, '', 'Get content node children count'
                ), 1
            ),
            'ezpObject' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/content/object/:objectId',
                    'OCOpenDataController',
                    'viewContent',
                    array(), null, '', 'Get content object by object id'
                ), 1
            ),
            'ezpFieldsByObject' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/content/object/:objectId/fields',
                    'OCOpenDataController',
                    'viewFields',
                    array(), null, '', 'Get content fields by object id'
                ), 1
            ),
            'ezpFieldByObject' => new OcOpenDataVersionedRoute(
                new OcOpenDataRoute(
                    '/content/object/:objectId/field/:fieldIdentifier',
                    'OCOpenDataController',
                    'viewField',
                    array(), null, '', 'Get content field by object id and field identifier'
                ), 1
            )
        );

        $routes['openDataListByClass'] = new OcOpenDataVersionedRoute(
        //'@^/content/class/(?P<classIdentifier>\w+)(?:/offset/(?P<offset>\d+))?(?:/limit/(?P<limit>\d+))?(?:/sort/(?P<sortKey>\w+)(?:/(?P<sortType>asc|desc))?)?$@',
            new OcOpenDataRegexpRoute(
                '@^/content/class/(?P<classIdentifier>\w+)(?:/offset/(?P<offset>\d+))?(?:/limit/(?P<limit>\d+))?$@',
                'OCOpenDataController',
                'listByClass',
                array(
                    'offset' => 0,
                    'limit' => 10
                ),
                null,
                'Get content node list by content class identifier'
            ), 1
        );

        $routes['openDataClassList'] = new OcOpenDataVersionedRoute(
            new OcOpenDataRoute(
                '/content/classList',
                'OCOpenDataController',
                'listClasses',
                array(), null, '', 'Get list of all content classes'
            ), 1
        );
        $routes['openDataInstantiatedClassList'] = new OcOpenDataVersionedRoute(
            new OcOpenDataRoute(
                '/content/instantiatedClassList',
                'OCOpenDataController',
                'instantiatedListClasses',
                array(), null, '', 'Get list of content classes used by the current instance'
            ), 1
        );

        $routes['openDataDataset'] = new OcOpenDataVersionedRoute(
            new OcOpenDataRoute(
                '/dataset',
                'OCOpenDataController',
                'datasetList',
                array(), null, '', 'Get list of dataset'
            ), 1
        );
        $routes['openDataDatasetView'] = new OcOpenDataVersionedRoute(
            new OcOpenDataRoute(
                '/dataset/:datasetId',
                'OCOpenDataController',
                'datasetView',
                array(), null, '', 'Get dataset by id'
            ), 1
        );

        return $routes;
    }

    /**
     * Returns associated with provider view controller
     *
     * @return ezpRestViewControllerInterface
     */
    public function getViewController()
    {
        return new OCOpenDataViewController();
    }

}
