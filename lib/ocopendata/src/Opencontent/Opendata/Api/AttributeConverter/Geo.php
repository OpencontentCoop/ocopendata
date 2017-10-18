<?php

namespace Opencontent\Opendata\Api\AttributeConverter;


use eZContentClassAttribute;
use eZContentObjectAttribute;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use Opencontent\Opendata\Api\PublicationProcess;

class Geo extends Base
{

    public function get( eZContentObjectAttribute $attribute )
    {
        $content = parent::get( $attribute );
        /** @var \eZGmapLocation $attributeContent */
        $attributeContent = $attribute->content();
        $content['content'] = array(
            'latitude' => (float)$attributeContent->attribute( 'latitude' ),
            'longitude' => (float)$attributeContent->attribute( 'longitude' ),
            'address' => $attributeContent->attribute( 'address' )
        );

        return $content;
    }

    public function set( $data, PublicationProcess $process )
    {
        $data = "1|#{$data['latitude']}|#{$data['longitude']}|#{$data['address']}";

        return parent::set($data, $process);
    }

    public static function validate($identifier, $data, eZContentClassAttribute $attribute)
    {
        if (is_array($data)) {
            if (!isset( $data['latitude'] ) || !isset( $data['longitude'] )) {
                throw new InvalidInputException('Invalid data', $identifier, $data);
            }

            if (!isset( $data['address'] )) {
                $data['address'] = '';
            }
        } else {
            throw new InvalidInputException('Invalid data', $identifier, $data);
        }
    }

    public function toCSVString($content, $params = null)
    {
        return $content['latitude'] . ',' . $content['longitude'];
    }
}
