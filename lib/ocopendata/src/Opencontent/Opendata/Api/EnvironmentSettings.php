<?php

namespace Opencontent\Opendata\Api;

use Opencontent\Opendata\Api\Exception\OutOfRangeException;
use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Api\Values\ContentClass;
use Opencontent\Opendata\Api\Values\SearchResults;
use Opencontent\QueryLanguage\QueryBuilder;
use Opencontent\Opendata\Api\Structs\ContentCreateStruct;
use Opencontent\Opendata\Api\Structs\ContentUpdateStruct;



class EnvironmentSettings
{
    protected $identifier;

    protected $defaultParentNodeId;

    protected $multimediaParentNodeId;

    protected $fileParentNodeId;

    protected $imageParentNodeId;

    protected $remoteIdPrefix;

    protected $validatorTolerance;

    protected $debug;

    protected $maxSearchLimit = 100;

    protected $defaultSearchLimit = 30;

    protected $requestBaseUri;

    /**
     * @var \ezpRestRequest
     */
    protected $request;

    public function __construct(array $properties = array())
    {
        foreach ($properties as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            } else {
                throw new OutOfRangeException($property);
            }
        }
    }

    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->{$property};
        }
        throw new OutOfRangeException($property);
    }

    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->{$property} = $value;
        } else {
            throw new OutOfRangeException($property);
        }
    }

    public static function __set_state(array $array)
    {
        return new static($array);
    }

    /**
     * @param Content $content
     *
     * @return array or array cast object
     */
    public function filterContent(Content $content)
    {
        return $content->jsonSerialize();
    }

    /**
     * @param SearchResults $searchResults
     *
     * @return SearchResults
     */
    public function filterSearchResult(SearchResults $searchResults, \ArrayObject $query, QueryBuilder $builder)
    {
        if ($searchResults->nextPageQuery != null && $this->requestBaseUri != null) {
            $searchResults->nextPageQuery = $this->requestBaseUri . 'search/' . urlencode($searchResults->nextPageQuery);
        }

        return $searchResults;
    }

    /**
     * @param \ArrayObject $query
     *
     * @return \ArrayObject
     */
    public function filterQuery(\ArrayObject $query, QueryBuilder $builder)
    {
        if (isset( $query['SearchLimit'] )) {
            if ($query['SearchLimit'] > $this->maxSearchLimit) {
                $query['SearchLimit'] = $this->maxSearchLimit;
            }
        } else {
            $query['SearchLimit'] = $this->defaultSearchLimit;
        }

        if (!isset( $query['SearchOffset'] )) {
            $query['SearchOffset'] = 0;
        }

        return $query;
    }

    /**
     * @param $data
     *
     * @return ContentCreateStruct
     */
    public function instanceCreateStruct( $data )
    {
        return ContentCreateStruct::fromArray( $data ) ;
    }

    public function afterCreate( $contentId, ContentCreateStruct $struct )
    {
    }

    /**
     * @param $data
     *
     * @return ContentUpdateStruct
     */
    public function instanceUpdateStruct( $data )
    {
        return ContentUpdateStruct::fromArray( $data );
    }

    public function afterUpdate( $contentId, ContentUpdateStruct $struct )
    {
    }

    /**
     * @return int
     */
    public function getMaxSearchLimit()
    {
        return $this->maxSearchLimit;
    }

    /**
     * @return mixed
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @return int
     */
    public function getDefaultSearchLimit()
    {
        return $this->defaultSearchLimit;
    }

    /**
     * @return mixed
     */
    public function getRequestBaseUri()
    {
        return $this->requestBaseUri;
    }

    /**
     * @return \ezpRestRequest
     */
    public function getRequest()
    {
        return $this->request;
    }


}
