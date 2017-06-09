<?php

namespace Opencontent\Opendata\Api\AttributeConverter;


use eZContentClassAttribute;
use eZContentObjectAttribute;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use eZTagsObject;
use Opencontent\Opendata\Api\PublicationProcess;

class Tags extends Base
{

    public function get( eZContentObjectAttribute $attribute )
    {
        $content = parent::get( $attribute );
        $tagsList = explode(', ', $attribute->metaData() );
        $tags = array();
        foreach ($tagsList as $tag) {
            if (!empty(trim($tag))){
                $tags[] = $tag;
            }
        }
        $content['content'] = $tags;
        return $content;
    }

    public function set( $data, PublicationProcess $process )
    {
        if (empty($data)){
            return null;
        }

        $tagIDs = array();
        $tagKeywords = array();
        $tagParents = array();

        foreach ((array)$data as $keyword) {

            $keywordsFound = eZTagsObject::fetchByKeyword($keyword);
            if ( !empty( $keywordsFound ) )
            {
                $tagIDs[] = $keywordsFound[0]->ID;
                $tagKeywords[] = $keywordsFound[0]->Keyword;
                $tagParents[] = $keywordsFound[0]->ParentID;
            }else{
                $tagIDs[] = 0;
                $tagKeywords[] = $keyword;
                $tagParents[] = 0;
            }
        }

        $tagIDs = implode( '|#', $tagIDs );
        $tagKeywords = implode( '|#', $tagKeywords );
        $tagParents = implode( '|#', $tagParents );

        $data = $tagIDs . '|#' . $tagKeywords . '|#' . $tagParents;
        return parent::set( $data, $process );
    }

    public static function validate( $identifier, $data, eZContentClassAttribute $attribute )
    {
        if ( is_array( $data ) )
        {
            foreach($data as $item){
                if (!is_string($item)){
                    throw new InvalidInputException( 'Invalid data', $identifier, $data );
                }
            }
        }
    }

    public function toCSVString($content, $params = null)
    {
        return implode(',', $content);
    }

    public function type(eZContentClassAttribute $attribute)
    {
        return array(
            'identifier' => 'tag',
            'format' => 'array'
        );
    }
}
