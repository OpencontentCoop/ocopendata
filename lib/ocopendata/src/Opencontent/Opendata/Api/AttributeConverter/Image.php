<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use eZURI;
use Opencontent\Opendata\Api\ClassRepository;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use Opencontent\Opendata\Api\PublicationProcess;

class Image extends File
{
    public function get( eZContentObjectAttribute $attribute )
    {
        $content = parent::get( $attribute );
        if ( $attribute instanceof eZContentObjectAttribute
             && $attribute->hasContent() )
        {
            /** @var \eZImageAliasHandler $attributeContent */
            $attributeContent = $attribute->content();
            $image = $attributeContent->attribute( 'original' );
            $url = $image['full_path'];
            eZURI::transformURI( $url, true );

            $content['content'] = array(
                'filename' => $image['original_filename'],
                'url' => $url,
                'alt' => $image['alternative_text'],
                'mime_type' => $image['mime_type'],
                'width' => $image['width'],
                'height' => $image['height']
            );
        }

        return $content;
    }

    public function set( $data, PublicationProcess $process )
    {
        if (!is_array($data)){
            $data = array(
                'url' => null,
                'file' => null,
                'filename' => null
            );
        }
        if ( !isset( $data['url'] ) )
        {
            $data['url'] = null;
        }

        if ( !isset( $data['file'] ) )
        {
            $data['file'] = null;
        }
        $path = null;

        if ( isset( $data['filename'] ) ) {
            $path = $this->getTemporaryFilePath($data['filename'], $data['url'], $data['file']);
            if (isset( $data['alt'] )) {
                $path .= '|' . $data['alt'];
            }
        }
        return $path;
    }

    public static function validate( $identifier, $data, eZContentClassAttribute $attribute )
    {
        if (is_string($data)){
            $data = null;
        }
        if ( is_array( $data ) && isset( $data['image'] ) )
        {
            $data['file'] = $data['image'];
        }
        parent::validate( $identifier, $data, $attribute );
        if ( isset( $data['alt'] ) && settype( $data['alt'], 'string' ) !== true )
        {
            throw new InvalidInputException( 'Invalid alt format', $identifier, $data );
        }
    }

    public function type( eZContentClassAttribute $attribute )
    {
        return array(
            'identifier' => 'file',
            'format' => array(
                'image' => 'public http uri',
                'file' => 'base64 encoded file (url alternative)',
                'filename' => 'string',
                'alt' => 'string'
            )
        );
    }

}
