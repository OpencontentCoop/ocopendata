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
            'openData2class' => new ezpRestVersionedRoute(
                new OcOpenDataRoute(
                    '/classes/:Identifier',
                    'OCOpenDataController2',
                    'classRead',
                    array(),
                    'http-get'
                ), 2
            ),
            'openData2classes' => new ezpRestVersionedRoute(
                new OcOpenDataRoute(
                    '/classes',
                    'OCOpenDataController2',
                    'classListRead',
                    array(),
                    'http-get'
                ), 2
            ),
            'openData2create' => new ezpRestVersionedRoute(
                new OcOpenDataRoute(
                    '/:EnvironmentSettigs/create',
                    'OCOpenDataController2',
                    'contentCreate',
                    array(),
                    'http-post'
                ), 2
            ),
            'openData2update' => new ezpRestVersionedRoute(
                new OcOpenDataRoute(
                    '/:EnvironmentSettigs/update',
                    'OCOpenDataController2',
                    'contentUpdate',
                    array(),
                    'http-post'
                ), 2
            ),
            'openData2delete' => new ezpRestVersionedRoute(
                new OcOpenDataRoute(
                    '/:EnvironmentSettigs/delete',
                    'OCOpenDataController2',
                    'contentDelete',
                    array(),
                    'http-post'
                ), 2
            ),
            'openData2download' => new ezpRestVersionedRoute(
                new OcOpenDataRoute(
                    '/download/:ObjectId/:Id/:Version/:Filename',
                    'OCOpenDataController2',
                    'contentDownload',
                    array(),
                    'http-post'
                ), 2
            )
        );

        foreach( EnvironmentLoader::getAvailablePresetIdentifiers() as $identifier )
        {
            if ( EnvironmentLoader::needAccess( $identifier ) )
            {
                $routes["openData2{$identifier}Read"] = new ezpRestVersionedRoute(
                    new OcOpenDataRoute(
                        "/{$identifier}/read/:ContentObjectIdentifier",
                        'OCOpenDataController2',
                        'protectedRead',
                        array(
                            'EnvironmentSettigs' => $identifier
                        ),
                        'http-get'
                    ), 2
                );
                $routes["openData2{$identifier}Search"] = new ezpRestVersionedRoute(
                    new OcOpenDataRoute(
                        "/{$identifier}/search/:Query",
                        'OCOpenDataController2',
                        'protectedSearch',
                        array(
                            'EnvironmentSettigs' => $identifier
                        ),
                        'http-get'
                    ), 2
                );
                $routes["openData2{$identifier}Browse"] = new ezpRestVersionedRoute(
                    new ezpMvcRegexpRoute(
                        '@^/'.$identifier.'/browse/(?P<ContentNodeIdentifier>\w+)@',
                        'OCOpenDataController2',
                        'protectedBrowse',
                        array(
                            'EnvironmentSettigs' => $identifier
                        )
                    ), 2
                );
            }
            else
            {
                $routes["openData2{$identifier}Read"] = new ezpRestVersionedRoute(
                    new OcOpenDataRoute(
                        "/{$identifier}/read/:ContentObjectIdentifier",
                        'OCOpenDataController2',
                        'anonymousRead',
                        array(
                            'EnvironmentSettigs' => $identifier
                        ),
                        'http-get'
                    ), 2
                );
                $routes["openData2{$identifier}Search"] = new ezpRestVersionedRoute(
                    new OcOpenDataRoute(
                        "/{$identifier}/search/:Query",
                        'OCOpenDataController2',
                        'anonymousSearch',
                        array(
                            'EnvironmentSettigs' => $identifier
                        )
                    ), 2
                );
                $routes["openData2{$identifier}Browse"] = new ezpRestVersionedRoute(
                    new ezpMvcRegexpRoute(
                        '@^/'.$identifier.'/browse/(?P<ContentNodeIdentifier>\w+)@',
                        'OCOpenDataController2',
                        'anonymousBrowse',
                        array(
                            'EnvironmentSettigs' => $identifier
                        ),
                        'http-get'
                    ), 2
                );
            }
        }
        return $routes;
    }

    public function getExtraRoutes()
    {
        $routes = array(
            'openData2tags' => new ezpRestVersionedRoute(
                new ezpMvcRegexpRoute(
                    '@^/tags_tree(?P<Tag>.+)@',
                    'OCOpenDataTagController',
                    'tagsTree',
                    array()
                ), 2
            )
        );
        return $routes;
    }

    public function getVersion1Routes()
    {
        $routes = array(
            'ezpListAtom' => new ezpRestVersionedRoute(
                new ezpMvcRailsRoute(
                    '/content/node/:nodeId/listAtom',
                    'ezpRestAtomController',
                    'collection'
                ), 1
            ),
            // @TODO : Make possible to interchange optional params positions
            'ezpList' => new ezpRestVersionedRoute(
                new ezpMvcRegexpRoute(
                    '@^/content/node/(?P<nodeId>\d+)/list(?:/offset/(?P<offset>\d+))?(?:/limit/(?P<limit>\d+))?(?:/sort/(?P<sortKey>\w+)(?:/(?P<sortType>asc|desc))?)?$@',
                    'OCOpenDataController',
                    'list',
                    array(
                        'offset' => 0,
                        'limit' => 10
                    )
                ), 1
            ),
            'ezpNode' => new ezpRestVersionedRoute(
                new ezpMvcRailsRoute(
                    '/content/node/:nodeId', 'OCOpenDataController', 'viewContent'
                ), 1
            ),
            'ezpFieldsByNode' => new ezpRestVersionedRoute(
                new ezpMvcRailsRoute(
                    '/content/node/:nodeId/fields',
                    'OCOpenDataController',
                    'viewFields'
                ), 1
            ),
            'ezpFieldByNode' => new ezpRestVersionedRoute(
                new ezpMvcRailsRoute(
                    '/content/node/:nodeId/field/:fieldIdentifier',
                    'OCOpenDataController',
                    'viewField'
                ), 1
            ),
            'ezpChildrenCount' => new ezpRestVersionedRoute(
                new ezpMvcRailsRoute(
                    '/content/node/:nodeId/childrenCount',
                    'OCOpenDataController',
                    'countChildren'
                ), 1
            ),
            'ezpObject' => new ezpRestVersionedRoute(
                new ezpMvcRailsRoute(
                    '/content/object/:objectId',
                    'OCOpenDataController',
                    'viewContent'
                ), 1
            ),
            'ezpFieldsByObject' => new ezpRestVersionedRoute(
                new ezpMvcRailsRoute(
                    '/content/object/:objectId/fields',
                    'OCOpenDataController',
                    'viewFields'
                ), 1
            ),
            'ezpFieldByObject' => new ezpRestVersionedRoute(
                new ezpMvcRailsRoute(
                    '/content/object/:objectId/field/:fieldIdentifier',
                    'OCOpenDataController',
                    'viewField'
                ), 1
            )
        );

        $routes['openDataListByClass'] = new ezpRestVersionedRoute(
            new ezpMvcRegexpRoute(
            //'@^/content/class/(?P<classIdentifier>\w+)(?:/offset/(?P<offset>\d+))?(?:/limit/(?P<limit>\d+))?(?:/sort/(?P<sortKey>\w+)(?:/(?P<sortType>asc|desc))?)?$@',
                '@^/content/class/(?P<classIdentifier>\w+)(?:/offset/(?P<offset>\d+))?(?:/limit/(?P<limit>\d+))?$@',
                'OCOpenDataController',
                'listByClass',
                array(
                    'offset' => 0,
                    'limit' => 10
                )
            ), 1
        );

        $routes['openDataClassList'] = new ezpRestVersionedRoute(
            new ezpMvcRailsRoute( '/content/classList', 'OCOpenDataController', 'listClasses' ), 1
        );
        $routes['openDataInstantiatedClassList'] = new ezpRestVersionedRoute(
            new ezpMvcRailsRoute(
                '/content/instantiatedClassList',
                'OCOpenDataController',
                'instantiatedListClasses'
            ), 1
        );

        $routes['openDataHelp'] = new ezpRestVersionedRoute(
            new ezpMvcRailsRoute( '/', 'OCOpenDataController', 'help' ), 1
        );
        $routes['openDataHelpList'] = new ezpRestVersionedRoute(
            new ezpMvcRailsRoute( '/help', 'OCOpenDataController', 'helpList' ), 1
        );

        $routes['openDataDataset'] = new ezpRestVersionedRoute(
            new ezpMvcRailsRoute( '/dataset', 'OCOpenDataController', 'datasetList' ), 1
        );
        $routes['openDataDatasetView'] = new ezpRestVersionedRoute(
            new ezpMvcRailsRoute( '/dataset/:datasetId', 'OCOpenDataController', 'datasetView' ), 1
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
