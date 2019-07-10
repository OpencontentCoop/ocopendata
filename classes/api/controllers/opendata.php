<?php

class OCOpenDataController extends ezpRestContentController
{

    /**
     * @var ezpRestRequest
     */
    protected $request;

    public function doHelp()
    {
        $result = new ezpRestMvcResult();
        $routesDescription = $this->getRoutesDescription();

        $apiPart = preg_quote( ezpRestPrefixFilterInterface::getApiPrefix(), '@' );
        preg_match("@^{$apiPart}/(?:(?P<provider>[^/]+)/)(?:(?P<version>v\d+))?@", $this->request->raw['REQUEST_URI'], $tokenMatches);
        $version = isset( $tokenMatches['version'] ) ? $tokenMatches['version'] : 'v' . count($routesDescription);

        if (!isset($routesDescription[$version])) {
            throw new Exception("Api version $version is not yet implemented");
        }
        $result->variables = $routesDescription[$version];
        return $result;
    }

    protected function getRoutesDescription()
    {
        $routes = array();
        foreach ($this->getRouter()->createRoutes() as $name => $route) {
            if ($route instanceof OcOpenDataVersionedRoute) {
                $innerRoute = $route->getRoute();
                if ($innerRoute instanceof OcOpenDataRouteInterface) {
                    $methods = array_keys($innerRoute->getProtocolActionMap());
                    $routes['v' . $route->getVersion()][] = array(
                        'api_description' => $innerRoute->getApiDescription(),
                        'api_url' => $route->generateDocUrl(),
                        'api_method' => array_shift($methods),
                    );
                }
            }
        }
        ksort($routes);

        return $routes;
    }

    public function doHelpList()
    {
        $result = new ezpRestMvcResult();
        $result->variables['help'] = array(
            array(
                'name' => 'Catalogo dei dataset disponibili',
                'identifier' => 'dataset',
                'link' => $this->request->getHostURI() . $this->getRouter()->generateUrl('openDataDataset'),
                'description' => 'Catalogo dei dataset accessibili'
            ),
            array(
                'name' => 'Lista della classi',
                'identifier' => 'classList',
                'link' => $this->request->getHostURI() . $this->getRouter()->generateUrl('openDataClassList'),
                'description' => 'Lista delle classi di contenuto accessibili'
            ),
            array(
                'name' => 'Lista della classi utilizzate',
                'identifier' => 'classList',
                'link' => $this->request->getHostURI() . $this->getRouter()->generateUrl('openDataInstantiatedClassList'),
                'description' => 'Lista delle classi di contenuto accessibili utilizzate dall\'istanza corrente'
            ),
        );
        return $result;
    }

    public function doDatasetList()
    {
        $result = new ezpRestMvcResult();
        $openDataTools = new OCOpenDataTools();
        $result->variables = $openDataTools->getDatasetIdArray();
        return $result;
    }

    public function doDatasetView()
    {
        $result = new ezpRestMvcResult();
        $openDataTools = new OCOpenDataTools();
        $result->variables = $openDataTools->getDataset($this->datasetId);
        return $result;
    }

    public function doInstantiatedListClasses()
    {
        $openDataTools = new OCOpenDataTools();
        $classes = $openDataTools->getUsedClassList();
        $result = new ezpRestMvcResult();
        $result->variables['classes'] = array();
        $result->variables['count'] = array(count($classes));
        foreach ($classes as $class) {

            // @todo
            $link = $this->request->getHostURI() . $this->getRouter()->generateUrl('openDataListByClass', array('classIdentifier' => $class->attribute('identifier')));
            $link = explode('(', $link);
            $link = $link[0];


            $result->variables['classes'][] = array(
                'identifier' => $class->attribute('identifier'),
                'name' => $class->attribute('name'),
                'link' => $link
            );
        }
        return $result;
    }

    public function doListClasses()
    {
        $openDataTools = new OCOpenDataTools();
        $classes = $openDataTools->getClassList();
        $result = new ezpRestMvcResult();
        $result->variables['classes'] = array();
        $result->variables['count'] = array(count($classes));
        foreach ($classes as $class) {

            // @todo
            $link = $this->request->getHostURI() . $this->getRouter()->generateUrl('openDataListByClass', array('classIdentifier' => $class->attribute('identifier'), 'offset' => 0, 'limit' => 10));
            $link = explode('(', $link);
            $link = $link[0];


            $result->variables['classes'][] = array(
                'identifier' => $class->attribute('identifier'),
                'name' => $class->attribute('name'),
                'link' => $link
            );
        }
        return $result;
    }

    public function doListByClass()
    {
        $nodeId = eZINI::instance('content.ini')->variable('NodeSettings', 'RootNode');

        $openDataTools = new OCOpenDataTools();
        $class = $openDataTools->getClass($this->classIdentifier);
        if (!$class instanceof eZContentClass) {
            throw new Exception('La classe non esiste');
        }
        $classIdentifier = $class->attribute('identifier');
        $className = $class->attribute('name');

        $this->setDefaultResponseGroups(array(self::VIEWLIST_RESPONSEGROUP_METADATA));
        $result = new ezpRestMvcResult();
        $crit = new ezpContentCriteria();

        // Location criteria
        // Hmm, the following sequence is too long...
        $crit->accept[] = ezpContentCriteria::location()->subtree(ezpContentLocation::fetchByNodeId($nodeId));
        $crit->accept[] = ezpContentCriteria::depth(20);
        $crit->accept[] = new ezpContentMainNodeOnlyCriteria(true);
        $crit->accept[] = ezpContentCriteria::contentClass()->is($classIdentifier);

        // Limit criteria
        $offset = isset($this->offset) ? $this->offset : 0;
        $limit = isset($this->limit) ? $this->limit : 10;
        if ($limit == 0) {
            $limit = 10;
        }
        if ($limit > 1000) {
            $limit = 10;
        }
        $crit->accept[] = ezpContentCriteria::limit()->offset($offset)->limit($limit);

        // Sort criteria
        /*
        if( isset( $this->sortKey ) )
        {
            $sortOrder = isset( $this->sortType ) ? $this->sortType : 'asc';
            $crit->accept[] = ezpContentCriteria::sorting( $this->sortKey, $sortOrder );
        }
        */
        $crit->accept[] = ezpContentCriteria::sorting('published', 'desc');

        $result->variables['nodes'] = OCOpenDataContentModel::getChildrenList($crit, $this->request, $this->getResponseGroups(), $this->getRouter());
        // REST links to children nodes
        // Little dirty since this should belong to the model layer, but I don't want to pass the router nor the full controller to the model
        $contentQueryString = $this->request->getContentQueryString(true);
        for ($i = 0, $iMax = count($result->variables['nodes']); $i < $iMax; ++$i) {
            $linkURI = $this->getRouter()->generateUrl('ezpNode', array('nodeId' => $result->variables['nodes'][$i]['nodeId']));
            $result->variables['nodes'][$i]['link'] = $this->request->getHostURI() . $linkURI . $contentQueryString;
        }

        // Handle Metadata
        if ($this->hasResponseGroup(self::VIEWLIST_RESPONSEGROUP_METADATA)) {
            $childrenCount = OCOpenDataContentModel::getChildrenCount($crit);
            $result->variables['metadata'] = array(
                'count' => $childrenCount,
                'rootNodeId' => $nodeId,
                'classIdentifier' => $classIdentifier,
                'className' => $className,
                'limit' => $limit,
                'offset' => $offset
            );

        }

        return $result;
    }

    public function doViewContent()
    {
        $this->setDefaultResponseGroups(array(self::VIEWCONTENT_RESPONSEGROUP_METADATA,
            self::VIEWCONTENT_RESPONSEGROUP_FIELDS,
            self::VIEWCONTENT_RESPONSEGROUP_LOCATIONS));
        $content = false;
        $isNodeRequested = false;
        if (isset($this->nodeId)) {
            $content = ezpContent::fromNodeId($this->nodeId);
            $isNodeRequested = true;
        } else if (isset($this->objectId)) {
            $object = eZContentObject::fetch($this->objectId);

            if (!$object instanceof eZContentObject) {
                $object = eZContentObject::fetchByRemoteID($this->objectId);
            }

            if ($object instanceof eZContentObject)
                $content = ezpContent::fromObject($object, true);
            else
                throw new ezpContentNotFoundException("Unable to find an eZContentObject with ID {$this->objectId}");
        }

        if (!$content instanceof ezpContent) {
            throw new ezpContentNotFoundException("Unable to find content");
        }

        $result = new ezpRestMvcResult();

        // translation parameter
        if ($this->hasContentVariable('Translation'))
            $content->setActiveLanguage($this->getContentVariable('Translation'));

        // Handle metadata
        if ($this->hasResponseGroup(self::VIEWCONTENT_RESPONSEGROUP_METADATA)) {
            $objectMetadata = OCOpenDataContentModel::getMetadataByContent($content);
            if ($isNodeRequested) {
                $nodeMetadata = OCOpenDataContentModel::getMetadataByLocation(ezpContentLocation::fetchByNodeId($this->nodeId));
                $objectMetadata = array_merge($objectMetadata, $nodeMetadata);
            }
            $result->variables['metadata'] = $objectMetadata;
        }

        // Handle locations if requested
        if ($this->hasResponseGroup(self::VIEWCONTENT_RESPONSEGROUP_LOCATIONS)) {
            $result->variables['locations'] = OCOpenDataContentModel::getLocationsByContent($content);
        }

        // Handle fields content if requested
        if ($this->hasResponseGroup(self::VIEWCONTENT_RESPONSEGROUP_FIELDS)) {
            $result->variables['fields'] = OCOpenDataContentModel::getFieldsByContent($content, $this->request, $this->getRouter());
        }

        // Add links to fields resources
        $result->variables['links'] = OCOpenDataContentModel::getFieldsLinksByContent($content, $this->request);

        if ($outputFormat = $this->getContentVariable('OutputFormat')) {
            $renderer = ezpRestContentRenderer::getRenderer($outputFormat, $content, $this);
            $result->variables['renderedOutput'] = $renderer->render();
        }

        return $result;
    }

    public function doViewField()
    {
        $this->setDefaultResponseGroups(array(self::VIEWFIELDS_RESPONSEGROUP_FIELDVALUES));

        $isNodeRequested = false;
        if (isset($this->nodeId)) {
            $isNodeRequested = true;
            $content = ezpContent::fromNodeId($this->nodeId);
        } else if (isset($this->objectId)) {
            $content = ezpContent::fromObjectId($this->objectId);
        }

        $realFieldIdentifier = OCOpenDataContentModel::getRealFieldIdentifier($this->fieldIdentifier, $content->classIdentifier);

        if (!isset($content->fields->{$realFieldIdentifier})) {
            throw new ezpContentFieldNotFoundException("'$realFieldIdentifier' field is not available for this content.");
        }

        // Translation parameter
        if ($this->hasContentVariable('Translation'))
            $content->setActiveLanguage($this->getContentVariable('Translation'));

        $result = new ezpRestMvcResult();

        // Field data
        if ($this->hasResponseGroup(self::VIEWFIELDS_RESPONSEGROUP_FIELDVALUES)) {
            $result->variables['fields'][$this->fieldIdentifier] = OCOpenDataContentModel::attributeOutputData($content->fields->{$realFieldIdentifier}, $this->request, $this->getRouter());
        }

        // Handle object/node metadata
        if ($this->hasResponseGroup(self::VIEWFIELDS_RESPONSEGORUP_METADATA)) {
            $objectMetadata = OCOpenDataContentModel::getMetadataByContent($content, $isNodeRequested);
            if ($isNodeRequested) {
                $nodeMetadata = OCOpenDataContentModel::getMetadataByLocation(ezpContentLocation::fetchByNodeId($this->nodeId));
                $objectMetadata = array_merge($objectMetadata, $nodeMetadata);
            }
            $result->variables['metadata'] = $objectMetadata;
        }
        return $result;
    }

    public function doViewFields()
    {
        $this->setDefaultResponseGroups(array(self::VIEWFIELDS_RESPONSEGROUP_FIELDVALUES));

        $isNodeRequested = false;
        if (isset($this->nodeId)) {
            $content = ezpContent::fromNodeId($this->nodeId);
            $isNodeRequested = true;
        } else if (isset($this->objectId)) {
            $content = ezpContent::fromObjectId($this->objectId);
        }

        $result = new ezpRestMvcResult();

        // translation parameter
        if ($this->hasContentVariable('Translation'))
            $content->setActiveLanguage($this->getContentVariable('Translation'));

        // Handle field values
        if ($this->hasResponseGroup(self::VIEWFIELDS_RESPONSEGROUP_FIELDVALUES)) {
            $result->variables['fields'] = OCOpenDataContentModel::getFieldsByContent($content, $this->request, $this->getRouter());
        }

        // Handle object/node metadata
        if ($this->hasResponseGroup(self::VIEWFIELDS_RESPONSEGORUP_METADATA)) {
            $objectMetadata = OCOpenDataContentModel::getMetadataByContent($content);
            if ($isNodeRequested) {
                $nodeMetadata = OCOpenDataContentModel::getMetadataByLocation(ezpContentLocation::fetchByNodeId($this->nodeId));
                $objectMetadata = array_merge($objectMetadata, $nodeMetadata);
            }
            $result->variables['metadata'] = $objectMetadata;
        }

        return $result;
    }
}

?>
