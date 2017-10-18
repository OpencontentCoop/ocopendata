<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use eZMail;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use Opencontent\Opendata\Api\PublicationProcess;


class Author extends Base
{
    public function get( eZContentObjectAttribute $attribute )
    {
        $content = parent::get( $attribute );
        $authorList = array();
        foreach ( $attribute->attribute( 'content' )->attribute( 'author_list' ) as $author )
        {
            $authorList[] = array(
                'name' => $author['name'],
                'email' => $author['email'],
            );
        }
        $content['content'] = $authorList;
        return $content;
    }

    public function set( $data, PublicationProcess $process )
    {
        $stringItems = array();
        foreach( $data as $author )
        {
            $stringItems[] = implode( '|', $author ) . '|-1';
        }
        return implode( '&', $stringItems );
    }

    public static function validate( $identifier, $data, eZContentClassAttribute $attribute )
    {
        if ( !is_array( $data ) )
        {
            throw new InvalidInputException( 'Invalid type', $identifier, $data );
        }

        foreach( $data as $item )
        {
            if ( !isset( $item['name'] ) || !isset( $item['email'] ) )
            {
                throw new InvalidInputException( 'Invalid type', $identifier, $data );
            }
            if ( !eZMail::validate( $item['email'] ) )
            {
                throw new InvalidInputException( 'Invalid email', $identifier, $data );
            }
        }
    }

    public function type( eZContentClassAttribute $attribute )
    {
        return array(
            'identifier' => 'array of objects',
            'format' => array(
                array(
                    'name' => 'string',
                    'email' => 'string'
                )
            )
        );
    }

    public function toCSVString($content, $language = null)
    {
        $data = array();
        foreach( $content as $authorList ){
            $data[] = $authorList['name'] . ' ' . $authorList['email'];
        }
        return implode("\n", $data);
    }
}
