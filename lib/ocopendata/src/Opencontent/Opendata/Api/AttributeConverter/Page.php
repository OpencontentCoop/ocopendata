<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use Opencontent\Opendata\Api\PublicationProcess;
use Opencontent\Opendata\Api\Values\Metadata;

class Page extends Base
{
    public function get( eZContentObjectAttribute $attribute )
    {
        $content = parent::get( $attribute );
        $data = array();
        if ( $attribute->hasContent() )
        {
            /** @var \eZPage $ezPage */
            $source = $attribute->attribute( 'data_text' );
            $page = \eZPage::createFromXML( $source );
            $data['zone_layout'] = $page->attribute( 'zone_layout' );
            /** @var \eZPageZone $zone */
            foreach ( $page->attribute( 'zones' ) as $zone )
            {
                $blocksData = array();
                /** @var \eZPageBlock[] $blocks */
                $blocks = (array)$zone->attribute( 'blocks' );
                foreach ( $blocks as $block )
                {
                    $validItems = array();
                    /** @var \eZContentObjectTreeNode[] $validNodes */
                    $validNodes = $block->attribute( 'valid_nodes' );
                    foreach( $validNodes as $node ){
                        $validItems[] = $node->object()->attribute( 'remote_id' );
                    }
                    $blocksData[] = array(
                        'block_id' => $block->attribute( 'id' ),
                        'name' => $block->attribute( 'name' ),
                        'type' => $block->attribute( 'type' ),
                        'view' => $block->hasAttribute( 'view' ) ? $block->attribute( 'view' ) : null,
                        'custom_attributes' => $block->attribute( 'custom_attributes' ),
                        'valid_items' => $validItems
                    );
                }
                $data[$zone->attribute( 'zone_identifier' )] = array(
                    'zone_id' => $zone->attribute( 'id' ),
                    'blocks' => $blocksData
                );
            }
        }
        $content['content'] = $data;

        return $content;
    }

    public function set( $data, PublicationProcess $process )
    {
        return null;
    }

    public static function validate( $identifier, $data, eZContentClassAttribute $attribute )
    {
        if ( $data !== null )
        {
            throw new InvalidInputException( "Readonly", $identifier, $data );
        }
    }

    public function type( eZContentClassAttribute $attribute )
    {
        return array(
            'identifier' => 'readonly'
        );
    }

    public function toCSVString($content, $params = null)
    {
        return ''; //todo
    }
}