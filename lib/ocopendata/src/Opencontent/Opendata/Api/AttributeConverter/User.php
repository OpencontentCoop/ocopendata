<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use eZUser;
use eZMail;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use Opencontent\Opendata\Api\PublicationProcess;
use Opencontent\Opendata\Api\Values\ContentData;

class User extends Base
{
    public function get( eZContentObjectAttribute $attribute )
    {
        $content = parent::get( $attribute );
        if ( $attribute->attribute( 'data_type_string' ) == 'ezuser'
             && $attribute instanceof eZContentObjectAttribute
             && $attribute->hasContent() )
        {
            /** @var eZUser $user */
            $user = $attribute->content();
            $content['content'] = array(
                'login' => $user->Login,
                'email' => $user->Email
            );
        }
        return $content;
    }

    public function set( $data, PublicationProcess $process )
    {
        return $data['login'] . '|' . $data['email'];
    }

    public static function validate( $identifier, $data, eZContentClassAttribute $attribute )
    {
        if ( !is_array( $data ) || !isset( $data['login'] ) || !isset( $data['email'] ) )
        {
            throw new InvalidInputException( 'Invalid type',$identifier, $data );
        }

        $user = eZUser::fetchByName( $data['login'] );
        if ( $user instanceof eZUser )
        {
            throw new InvalidInputException( 'Duplicate user login', $identifier, $data );
        }

        $user = eZUser::fetchByEmail( $data['email'] );
        if ( $user instanceof eZUser )
        {
            throw new InvalidInputException( 'Duplicate user email', $identifier, $data );
        }

        if ( !eZMail::validate( $data['email'] ) )
            throw new InvalidInputException( 'Invalid email', $identifier, $data );
    }

    public function type( eZContentClassAttribute $attribute )
    {
        return array(
            'identifier' => 'user',
            'format' => array(
                'login' => 'string',
                'email' => 'string'
            )
        );
    }
}