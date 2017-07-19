<?php


class ezfIndexOpenDataStorage implements ezfIndexPlugin
{
    /**
     * @param eZContentObject $contentObject
     * @param eZSolrDoc[] $docList
     */
    public function modify( eZContentObject $contentObject, &$docList )
    {
        if ( class_exists( '\Opencontent\Opendata\Api\Gateway\SolrStorage') )
        {
            try
            {
                $dbGateway = new \Opencontent\Opendata\Api\Gateway\Database();
                $content = $dbGateway->loadContent( $contentObject->attribute( 'id' ) );
                $solrStorage = new ezfSolrStorage();
                $value = $solrStorage->serializeData( $content->jsonSerialize() );
                $identifier = $solrStorage->getSolrStorageFieldName( 'opendatastorage' );

                foreach ( $content->metadata->languages as $languageCode )
                {
                    if ( $docList[$languageCode] instanceof eZSolrDoc )
                    {
                        if ( $docList[$languageCode]->Doc instanceof DOMDocument )
                        {
                            $xpath = new DomXpath( $docList[$languageCode]->Doc );
                            if ( $xpath->evaluate(
                                    '//field[@name="' . $identifier . '"]'
                                )->length == 0
                            )
                            {
                                $docList[$languageCode]->addField( $identifier, $value );
                            }
                        }
                        elseif ( is_array(
                                     $docList[$languageCode]->Doc
                                 )
                                 && !isset( $docList[$languageCode]->Doc[$identifier] )
                        )
                        {
                            $docList[$languageCode]->addField( $identifier, $value );
                        }
                    }
                }
            }
            catch( Exception $e )
            {
                eZDebug::writeError( $e->getMessage(), __METHOD__ );
            }
        }
    }
}
