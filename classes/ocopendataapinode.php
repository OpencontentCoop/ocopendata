<?php
class OCOpenDataApiNode implements ArrayAccess
{
    protected $container;

    public static function fromLink( $url )
    {
        $data = json_decode( eZHTTPTool::getDataByURL( $url ), true );
        if ( $data )
        {
            return new self( $data );
        }
        return false;
    }
    
    public function __construct( $item )
    {
        $this->container = $item;
    }
    
    public function __get( $name )
    {
        if ( isset( $this->container[$name] ) )
        {
            return $this->container[$name];
        }
        return false;
    }
    
    public function searchLocal( $useRemote = true, $parentNode = false )
    {
        $object = null;

        if ( $useRemote )
        {
            $object = eZContentObject::fetchByRemoteID( $this->metadata['objectRemoteId'] );
        }
        else
        {
            if ( !$parentNode )
            {
                $parentNode = eZINI::instance( 'content.ini' )->variable( 'NodeSettings', 'RootNode' );
            }
            
            $params = array(
                'SearchLimit' => 1,
                'Filter' => null,
                'SearchContentClassID' => array( $this->metadata['classIdentifier']  ),
                'SearchSubTreeArray' => array( $parentNode ),
                'Limitation' => array()
            );        
            $solrSearch = new eZSolr();
            $search = $solrSearch->search( '"' . $this->metadata['objectName'] . '"', $params );
            if ( $search['SearchCount'] > 0 )
            {
                $resultNode = $search['SearchResult'][0];
                $object = eZContentObject::fetch( $resultNode->attribute( 'contentobject_id' ) );
            }
            else
            {
                
            }
        }
        
        return $object;
    }
    
    public function compareWithContentObject( eZContentObject $object = null, $classTools = null )
    {
        if ( !$object instanceof eZContentObject )
        {
            throw new Exception( 'Oggetto non trovato' );
        }
        if ( $this->metadata['classIdentifier'] !== $object->attribute( 'class_identifier' ) )
        {
            throw new Exception( "L'oggetto con remote id {$object->attribute( 'class_identifier' )} è di classe diversa rispetto all'oggetto remoto" );
        }
        if ( is_object( $classTools ) && method_exists( $classTools, 'isValid' ) )
        {
            if ( !$classTools->isValid() )
            {
                throw new Exception( "La classe {$object->attribute( 'class_identifier' )} non ha passato la validazione" );
            }
        }
    }
    
    public function createContentObject( $parentNodeID, $localRemoteIdPrefix = '' )
    {
        if ( !eZContentClass::fetchByIdentifier( $this->metadata['classIdentifier'] ) )
        {
            throw new Exception( "La classe {$this->metadata['classIdentifier']} non esiste in questa installazione" );
        }

        if ( eZContentObject::fetchByRemoteID( $this->metadata['objectRemoteId'] ) )
        {
            throw new Exception( "L'oggetto con remote \"{$this->metadata['objectRemoteId']}\" esiste già in questa installazione" );            
        }
        
        $searchEngine = new eZSolr();
        $searchParams = array( 'SearchContentClassID' => $this->metadata['classIdentifier'],
                               'SearchLimit' => 1,
                               'Filter' => array( 'or', 'meta_name_t:"' . $this->metadata['objectName'] . '"' ),
                               'SearchSubTreeArray' => array( $parentNodeID ) );
        
        $search = $searchEngine->search( '', $searchParams);
        if ( $search['SearchCount'] > 0 )
        {            
            throw new Exception( "Sembra che esista già un oggetto con nome \"{$this->metadata['objectName']}\" in {$parentNodeID}" );
        }        
        

        $params                     = array();        
        $params['class_identifier'] = $this->metadata['classIdentifier'];
        $params['remote_id']        = $localRemoteIdPrefix . $this->metadata['objectRemoteId'];
        $params['parent_node_id']   = $parentNodeID;
        $params['attributes']       = $this->getAttributesStringArray( $parentNodeID );
        return eZContentFunctions::createAndPublishObject( $params );
    }
    
    public function updateLocalRemoteId( eZContentObject $object = null, $localRemoteIdPrefix = null, $classTools = null )
    {
        if ( $localRemoteIdPrefix !== null )
        {
            $remoteId = $localRemoteIdPrefix . $this->metadata['objectRemoteId'];            
            if ( $object->attribute( 'remote_id' ) != $remoteId )
            {
                $this->compareWithContentObject( $object, $classTools );
                $object->setAttribute( 'remote_id', $remoteId );
                $object->store();
                return true;
            }            
        }
        return false;
    }
    
    public function updateContentObject( eZContentObject $object = null, $classTools = null, $localRemoteIdPrefix = '' )
    {
        if ( $object === null )
        {
            $object = eZContentObject::fetchByRemoteID( $localRemoteIdPrefix . $this->metadata['objectRemoteId'] );
        }
        $this->compareWithContentObject( $object, $classTools );
        $params = array();        
        $params['attributes'] = $this->getAttributesStringArray( $object->attribute( 'main_parent_node_id' ), true );
        $newObject = eZContentFunctions::updateAndPublishObject( $object, $params );
        if ( !$newObject )
        {
            throw new Exception( "Errore sincronizzando l'oggetto" );
        }
        $objectId = $object->attribute( 'id' );
        eZContentObject::clearCache( array( $objectId ) );
        return eZContentObject::fetch( $objectId );
    }
    
    public function getAttributesStringArray( $parentNodeID, $isUpdate = false )
    {
        $attributeList = array();
        foreach( (array) $this->fields as $identifier => $fieldArray )
        {
            switch( $fieldArray['type'] )
            {
                case 'ezxmltext':
                    $attributeList[$identifier] = SQLIContentUtils::getRichContent( $fieldArray['value'] );
                    break;
                case 'ezbinaryfile':
                case 'ezimage':
                    if ( !empty( $fieldArray['value'] ) )
                    {
                        $attributeList[$identifier] = SQLIContentUtils::getRemoteFile( $fieldArray['value'] );
                    }
                    break;
                case 'ezobjectrelationlist':
                    $parentNodeID = $this->findRelationObjectLocation( $identifier, $parentNodeID );
                    $attributeList[$identifier] = $this->createRelationObjects( $fieldArray, $parentNodeID, $isUpdate );
                    break;
                default:
                    $attributeList[$identifier] = $fieldArray['string_value'];
                    break;
            }            
        }
        return $attributeList;
    }

    protected function findRelationObjectLocation( $identifier, $parentNodeID )
    {
        $ini = eZINI::instance( 'ocopendata.ini' );
        if ( $ini->hasVariable( 'CreateContentSettings', 'RelationCreateParentNode' ) )
        {
            $settings = $ini->variable( 'CreateContentSettings', 'RelationCreateParentNode' );
            $key = $this->metadata['classIdentifier'] . '/' . $identifier;
            if ( isset( $settings[$key] ) )
            {
                $customParentNodeID = $settings[$key];
                if ( eZContentObjectTreeNode::fetch( $customParentNodeID ) )
                {
                    return $customParentNodeID;
                }
            }
        }
        return $parentNodeID;
    }

    protected function createRelationObjects( $fieldArray, $parentNodeID, $isUpdate = false )
    {
        $data = array();
        if ( is_array( $fieldArray['value'] ) )
        {
            foreach ( $fieldArray['value'] as $item )
            {
                try{
                    
                    // Nella variabile $link imposto il link all'oggetto
                    $link = '';
                    if( is_array($item) ){
                        if( array_key_exists ( 'link' , $item ) && substr( $item['link'], 0, 4 ) === "http" ){
                            $link = $item['link'];
                        }
                    }
                    else if( substr( $item, 0, 4 ) === "http" ){
                        $link = $item;
                    }
                    
                    if($link != "" && strpos( $link, "/api/" ) !== false ){
                        
                        $remoteApiNode = OCOpenDataApiNode::fromLink( $link );
                        
                        if ( !$remoteApiNode instanceof OCOpenDataApiNode )
                        {
                            throw new Exception( "Api node not found" );
                        }
                        if ( $isUpdate )
                        {
                            $newObject = $remoteApiNode->updateContentObject();
                        }
                        else
                        {
                            $newObject = $remoteApiNode->createContentObject( $parentNodeID );
                        }
                        if ( $newObject instanceof eZContentObject )
                        {
                            $data[] = $newObject->attribute( 'id' );
                        }
                    }
                }
                catch ( Exception $e )
                {
                    eZDebug::writeError(
                        $e->getMessage() . ' ' . var_export( $item, 1 ),
                        __METHOD__
                    );
                }
            }
        }
        //eZDebug::writeNotice( var_export( $data, 1 ), __METHOD__ );
        return implode( '-', $data );
    }
    
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }
    
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }
    
    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }
    
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }
}
