<?php

namespace Opencontent\Opendata\Api\Gateway;
use Opencontent\Opendata\Api\Gateway;
use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Api\Exception\NotFoundException;
use eZContentObject;

class Database implements Gateway
{
    /**
     * @param $contentObjectIdentifier
     *
     * @return Content
     * @throws NotFoundException
     */
    public function loadContent( $contentObjectIdentifier )
    {
        $contentObject = self::findContent( $contentObjectIdentifier );
        $content = Content::createFromEzContentObject( $contentObject );
        return $content;
    }

    /**
     * @param $contentObjectIdentifier
     *
     * @return eZContentObject|null
     * @throws NotFoundException
     */
    protected static function findContent( $contentObjectIdentifier )
    {
        $contentObject = eZContentObject::fetch( intval( $contentObjectIdentifier ) );
        if ( !$contentObject instanceof eZContentObject )
        {
            $contentObject = eZContentObject::fetchByRemoteID( $contentObjectIdentifier );
        }
        if ( !$contentObject instanceof eZContentObject )
        {
            throw new NotFoundException( $contentObjectIdentifier );
        }
        return $contentObject;
    }
}