<?php

namespace Opencontent\Opendata\Api\Gateway;

use eZSolr;
use ezfSolrDocumentFieldBase;
use ezfSolrStorage;
use eZDB;
use Opencontent\Opendata\Api\Gateway;
use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Api\Values\ContentData;
use Opencontent\Opendata\Api\Values\Metadata;
use Opencontent\Opendata\Api\Exception\NotFoundException;
use Opencontent\Opendata\Api\Exception\ForbiddenException;

class SolrStorage implements Gateway
{
    /**
     * @var eZSolr
     */
    protected $solr;

    public function __construct()
    {
        $this->solr = new eZSolr();
    }

    public function loadContent( $contentObjectIdentifier )
    {
        $content = $this->findContent( $contentObjectIdentifier );

        return $content;
    }

    public static function getSolrIdentifier()
    {
        $solrStorageTools = new ezfSolrStorage();
        return $solrStorageTools->getSolrStorageFieldName( 'opendatastorage' );
    }

    protected function findContent( $contentObjectIdentifier )
    {
        $search = $this->solr->search(
            '',
            array(
                'Filter' => array(
                    ezfSolrDocumentFieldBase::generateMetaFieldName('installation_id') . ':' . eZSolr::installationID(),
                    array(
                        'or',
                        ezfSolrDocumentFieldBase::generateMetaFieldName( 'id' ) . ':' . (int) $contentObjectIdentifier,
                        ezfSolrDocumentFieldBase::generateMetaFieldName( 'remote_id' ) . ':' . $contentObjectIdentifier
                    )
                ),
                'AsObjects' => false,
                'FieldsToReturn' => array( self::getSolrIdentifier() ),
                'Limitation' => array()
            )
        );

        $content = null;

        if ( $search['SearchCount'] > 0 )
        {
            if ( isset( $search['SearchResult'][0]['data_map']['opendatastorage'] ) )
            {
                $contentArray = $search['SearchResult'][0]['data_map']['opendatastorage'];
                $content = new Content();
                $content->metadata = new Metadata( $contentArray['metadata'] );
                $content->data = new ContentData( $contentArray['data'] );
            }
        }

        if ( !$content instanceof Content )
        {
            $gateway = new Database();
            $content = $gateway->loadContent( $contentObjectIdentifier );
            $id = $content->metadata->id;
            eZDB::instance()->query(
                "INSERT INTO ezpending_actions( action, param ) VALUES ( 'index_object', $id )"
            );
        }

        return $content;
    }
}