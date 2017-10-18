<?php

class OCOpenDataContentModel extends ezpRestContentModel
{    
    public static function getMetadataByLocation( ezpContentLocation $location )
    {
        $url = $location->url_alias;
        eZURI::transformURI( $url, false, 'full' ); // $url is passed as a reference
        
        $aMetadata = array(
            'nodeId'        => (int)$location->node_id,
            'nodeRemoteId'  => $location->remote_id,
            'fullUrl'       => $url
        );
        
        try
        {
            $node = eZContentObjectTreeNode::fetch( $location->node_id );
            if ( $node instanceof eZContentObjectTreeNode )
            {                
                $pathNames = explode( '/', $node->attribute( 'path_with_names' ) );                
                $aMetadata['path'] = $pathNames;
            }
        }
        catch( Exception $e )
        {
            
        }
        
        return $aMetadata;
    }
    
    public static function getChildrenList( ezpContentCriteria $c, ezpRestRequest $currentRequest, array $responseGroups = array(), ezcMvcRouter $router = null )
    {
        $aRetData = array();
        /** @var ezpContent[] $aChildren */
        $aChildren = ezpContentRepository::query( $c );

        $fieldBlacklist = self::getFieldBlacklist();
        
        foreach ( $aChildren as $childNode )
        {
            $childEntry = self::getMetadataByContent( $childNode );
            $childEntry = array_merge( $childEntry, self::getMetadataByLocation( $childNode->locations ) );

            // Add fields with their values if requested
            if ( in_array( ezpRestContentController::VIEWLIST_RESPONSEGROUP_FIELDS, $responseGroups ) )
            {
                $childEntry['fields'] = array();
                foreach ( $childNode->fields as $fieldName => $field )
                {
                    if ( !isset( $fieldBlacklist[$fieldName] ) && !isset( $fieldBlacklist[$childNode->classIdentifier . '/' . $fieldName] ) )
                    {
                        $childEntry['fields'][self::getOverrideFieldIdentifier( $fieldName, $childNode->classIdentifier)] = self::attributeOutputData( $field, $currentRequest, $router );
                    }
                }
            }

            $aRetData[] = $childEntry;
        }

        return $aRetData;
    }
    
    public static function getFieldsByContent( ezpContent $content, ezpRestRequest $currentRequest = null, ezcMvcRouter $router = null )
    {
        $fieldBlacklist = self::getFieldBlacklist();
        $aReturnFields = array();
        if ( $content->fields instanceof ezpContentFieldSet && $content->fields->valid() )
        {
            foreach ( $content->fields as $name => $field )
            {
                if ( !isset( $fieldBlacklist[$name] ) && !isset( $fieldBlacklist[$content->classIdentifier . '/' . $name] ) )
                {
                    $aReturnFields[self::getOverrideFieldIdentifier(
                        $name,
                        $content->classIdentifier
                    )] = self::attributeOutputData( $field, $currentRequest, $router );
                }
            }
        }

        return $aReturnFields;
    }
    
    public static function getFieldsLinksByContent( ezpContent $content, ezpRestRequest $currentRequest )
    {
        $fieldBlacklist = self::getFieldBlacklist();
        $links = array();

        $baseUri = isset($currentRequest->raw['SCRIPT_URI'])?$currentRequest->raw['SCRIPT_URI']:null;
        //$baseUri = $currentRequest->getBaseURI();
        $contentQueryString = $currentRequest->getContentQueryString( true );

        foreach ( $content->fields as $fieldName => $fieldValue )
        {
            if ( !isset( $fieldBlacklist[$fieldName] ) && !isset( $fieldBlacklist[$content->classIdentifier . '/' . $fieldName] ) )
            {
                $links[self::getOverrideFieldIdentifier( $fieldName, $content->classIdentifier)] = $baseUri.'/field/'.self::getOverrideFieldIdentifier( $fieldName, $content->classIdentifier).$contentQueryString;
            }
        }
        $links['*'] = $baseUri.'/fields'.$contentQueryString;

        return $links;
    }
    
    public static function attributeOutputData( ezpContentField $field, ezpRestRequest $currentRequest = null, ezcMvcRouter $router = null )
    {
        $attributeValue = $stringValue = array();
        switch( $field->data_type_string )
        {
            case 'ezxmltext':
                $html = $field->content->attribute( 'output' )->attribute( 'output_text' );
                $attributeValue = array( $html );
                $stringValue = array( strip_tags( $html ) );
                break;
            case 'ezimage':
                if ( $field->hasContent() )
                {
                    $strRepImage = $field->toString();
                    $delimPos = strpos( $strRepImage, '|' );
                    if ( $delimPos !== false )
                    {
                        $strRepImage = substr( $strRepImage, 0, $delimPos );
                    }
                    $attributeValue = array( self::getHostURIFromRequest( $currentRequest ) . '/' .$strRepImage );
                    $stringValue = array( $field->toString() );
                }
                break;
            case 'ezbinaryfile':                
                if ( $field->hasContent() )
                {
                    $file = $field->content();
                    $filePath = "content/download/{$field->attribute('contentobject_id')}/{$field->attribute('id')}/{$field->content()->attribute( 'original_filename' )}";
                    $attributeValue = array( self::getHostURIFromRequest( $currentRequest ) . '/' . $filePath );
                    $stringValue = array( $field->toString() );
                }
                else
                {
                    $attributeValue = array( null );
                    $stringValue = array( null );
                }
                break;
            case 'ezobjectrelationlist':
                $attributeValue = array();
                $stringValueArray = array();
                if ( $currentRequest && $router)
                {
                    if ( $field->hasContent() )
                    {
                        $relations =  $field->content();                        
                        foreach( $relations['relation_list'] as $relation )
                        {                            
                            $id = $relation['contentobject_id'];
                            $object = eZContentObject::fetch( $id );
                            
                            if ( $object instanceof eZContentObject && $object->attribute( 'main_node' ) instanceof eZContentObjectTreeNode )
                            {                                
                                if ( $object->attribute( 'can_read' ) )                                    
                                {
                                    $content = ezpContent::fromObject( $object );
                                    $objectMetadata = OCOpenDataContentModel::getMetadataByContent( $content );
                                    $contentQueryString = $currentRequest->getContentQueryString( true );
                                    try
                                    {
                                        if ( $content->main_node )
                                        {
                                            $node = $content->main_node;
                                            $location = ezpContentLocation::fromNode( $node );
                                            $objectMetadata = array_merge( $objectMetadata, self::getMetadataByLocation( $location ) );
                                            $stringValueArray[] = $id;
                                        }
                                        else
                                        {
                                            throw new Exception( "Node not found for object id #$id" );
                                        }
                                    }
                                    catch( Exception $e )
                                    {
                                        
                                    }
                                    $objectMetadata['link'] = self::getHostURIFromRequest( $currentRequest ) . $router->generateUrl( 'ezpObject', array( 'objectId' => $id ) ) . $contentQueryString;
                                    $attributeValue[] = $objectMetadata;
                                }
                                //else
                                //{
                                //    $attributeValue[] = "Access not allowed for content $id";
                                //}
                            }
                        }
                    }
                }
                $stringValue = array( implode( '-', $stringValueArray ) );
                break;
            case 'ezobjectrelation':
                $attributeValue = array();
                $stringValue = array( $field->toString() );
                if ( $currentRequest && $router)
                {
                    if ( $field->hasContent() )
                    {
                        $relation =  $field->content();
                        if ( $relation->attribute( 'can_read' ) )
                        {    
                            $id = $relation->attribute( 'id' );
                            $content = ezpContent::fromObject( $relation );
                            $objectMetadata = OCOpenDataContentModel::getMetadataByContent( $content );
                            $objectMetadata['link'] = self::getHostURIFromRequest( $currentRequest ) . $router->generateUrl( 'ezpObject', array( 'objectId' => $id ) );
                            $attributeValue[] = $objectMetadata;
                        }
                    }
                }                
                break;
            default:
                $datatypeBlacklist = self::getDatatypeBlackList();
                if ( isset ( $datatypeBlacklist[$field->data_type_string] ) )
                {
                    $attributeValue = array( null );
                    $stringValue = array( null );
                }
                elseif ( $field->hasContent() )
                {
                    $attributeValue = array( $field->toString() );
                    $stringValue = array( $field->toString() );
                }
                break;
        }

        if ( count( $attributeValue ) == 0 )
        {
            $attributeValue = false;
        }
        elseif ( count( $attributeValue ) == 1 )
        {
            $attributeValue = current( $attributeValue );
        }
        
        if ( count( $stringValue ) == 0 )
        {
            $stringValue = false;
        }
        elseif ( count( $stringValue ) == 1 )
        {
            $stringValue = current( $stringValue );
        }
        
        $return = array(            
            'name'                  => $field->contentclass_attribute_name,
            'description'           => $field->contentclass_attribute->attribute( 'description' ),
            'identifier'            => $field->contentclass_attribute_identifier,
            'id'                    => (int)$field->id,
            'classattribute_id'     => (int)$field->contentclassattribute_id,
            'type'                  => $field->data_type_string,            
            'value'                 => $attributeValue,
            'string_value'          => $stringValue,            
        );
        
        return $return;
    }
    
    public static function getDatatypeBlackList()
    {
        return OCOpenDataTools::getDatatypeBlackList();        
    }
    
    public static function getFieldBlacklist()
    {
        return OCOpenDataTools::getFieldBlacklist();
    }

    /**
     * @param string $fieldName
     * @param string $classIdentifier
     *
     * @return string
     */
    public static function getOverrideFieldIdentifier( $fieldName, $classIdentifier )
    {
        return OCOpenDataTools::getOverrideFieldIdentifier( $fieldName, $classIdentifier );
    }
    
    public static function getRealFieldIdentifier( $fieldName, $classIdentifier )
    {
        return OCOpenDataTools::getRealFieldIdentifier( $fieldName, $classIdentifier );
    }

    protected static function getHostURIFromRequest( ezpRestRequest $request )
    {
        $protocolIndex = strpos( $request->protocol, '-' );
        $protocol = substr( $request->protocol, 0, $protocolIndex );
        $protocol .= $request->isEncrypted ? 's' : '';
        $hostUri = $protocol.'://'.$request->host;
        return $hostUri;
    }
    
}

?>
