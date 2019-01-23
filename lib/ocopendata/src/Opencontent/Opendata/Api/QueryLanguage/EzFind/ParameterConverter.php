<?php

namespace Opencontent\Opendata\Api\QueryLanguage\EzFind;

use Opencontent\QueryLanguage\Converter\Exception;
use Opencontent\QueryLanguage\Parser\Parameter;
use Opencontent\QueryLanguage\Parser\Sentence;
use eZFindExtendedAttributeFilterFactory;
use eZFindExtendedAttributeFilterInterface;

class ParameterConverter extends SentenceConverter
{

    /**
     * @param Sentence $parameter
     *
     * @return void
     * @throws Exception
     */
    public function convert(Sentence $parameter)
    {
        if ($parameter instanceof Parameter) {
            $key = (string)$parameter->getKey();
            $value = $parameter->getValue();

            switch ($key) {
                case 'classes':
                    $this->convertClasses($value);
                    break;

                case 'sort': {
                    $this->convertSortBy($value);
                }
                    break;

                case 'geosort': {
                    $this->convertGeoSort($value);
                }
                    break;

                case 'limit': {
                    $this->convertLimit($value);
                }
                    break;

                case 'offset': {
                    $this->convertOffset($value);
                }
                    break;

                case 'subtree': {
                    $this->convertSubtree($value);
                }
                    break;

                case 'facets': {
                    $this->convertFacets($value);
                }
                    break;

                case 'select-fields': {
                    $this->convertSelect($value);
                }
                    break;

                case 'language': {
                    $this->convertLanguage($value);
                }
                    break;

                default:
                    $attributeFilter = eZFindExtendedAttributeFilterFactory::getInstance($key);
                    if ($attributeFilter instanceof eZFindExtendedAttributeFilterInterface){
                        $this->convertExtendedAttributeFilter($attributeFilter, $key, $value);
                    }else{                    
                        throw new Exception("Can not convert $key parameter");
                    }
            }
        }
    }

    protected function convertClasses($value)
    {
        if (!is_array($value)) {
            $value = array($value);
        }
        $list = array();
        foreach ($value as $item) {
            $list[] = trim($item, "'");
        }

        $idList = array();
        foreach ($list as $class) {
            if (!in_array($class, $this->classRepository->listClassIdentifiers())) {
                throw new Exception("Class $class not found");
            }
            $idList[] = $this->classRepository->load($class)->getClassId();
        }
        $this->solrNamesHelper->filterAvailableFieldDefinitionsByClasses($list);
        $this->convertedQuery['SearchContentClassID'] = array_map('intval', $idList);
    }

    protected function convertSortBy($value)
    {
        if (is_array($value)) {
            $data = array();
            foreach ($value as $field => $order) {
                if (!in_array($order, array('asc', 'desc'))) {
                    throw new Exception("Can not convert sort order value: $order");
                }
                if ( $field == 'name' ) {
                    $fieldNames = array( 'sort_name' );
                }
                elseif ( $field == 'score' ) {
                    $fieldNames = array( 'score' );
                } else {
                    $fieldNames = $this->solrNamesHelper->generateSortNames($field);
                }
                foreach ($fieldNames as $name) {
                    $data[$name] = $order;
                }

            }
            $this->convertedQuery['SortBy'] = $data;
        } else {
            throw new Exception("Sort parameter require an hash value");
        }
    }

    protected function convertGeoSort($value)
    {
        if (!class_exists('eZFindGeoDistExtendedAttributeFilter')) {
            throw new Exception("geo extended attribute filter not found: the server eZFind version is outdated");
        }

        if (is_array($value) && count($value) == 2) {

            $fields = $this->solrNamesHelper->getIdentifiersByDatatype('ezgmaplocation');
            if (count($fields) > 1) {
                throw new Exception("There are ambigous geo identifiers (" . implode(', ',
                        $fields) . "): please reduce them using the 'classes' parameter (or fix your classes if you can)");
            }
            $field = $this->solrNamesHelper->generateSolrSubFieldName($fields[0], 'coordinates', 'geopoint');

            $extendedFilters = array();

            $latitude = (float)$value[0];
            $longitude = (float)$value[1];

            if (\eZINI::instance('ocopendata.ini')->hasVariable('DevSettings', 'SolrGmapLocationBugWorkround')
                && \eZINI::instance('ocopendata.ini')->variable('DevSettings',
                    'SolrGmapLocationBugWorkround') == 'enabled'
            ) {
                $latitude = (float)$value[1];
                $longitude = (float)$value[0];
            }

            $extendedFilter = array(
                'id' => 'geodist',
                'params' => array(
                    'field' => $field,
                    'latitude' => $latitude,
                    'longitude' => $longitude
                )
            );
            if (!isset( $this->convertedQuery['ExtendedAttributeFilter'] )) {
                $this->convertedQuery['ExtendedAttributeFilter'] = array();
            }
            $this->convertedQuery['ExtendedAttributeFilter'][] = $extendedFilter;
        } else {
            throw new Exception("Geosort parameter require a LatLon array");
        }
    }

    protected function convertLimit($value)
    {
        if (is_array($value)) {
            throw new Exception("Limit parameter require an integer value");
        }
        $this->convertedQuery['SearchLimit'] = intval($value);
    }

    protected function convertOffset($value)
    {
        if (is_array($value)) {
            throw new Exception("Offset parameter require an integer value");
        }
        $this->convertedQuery['SearchOffset'] = intval($value);
    }

    protected function convertSubtree($value)
    {
        if (!is_array($value)) {
            $value = array($value);
        }
        $value = array_map('intval', $value);
        $this->convertedQuery['SearchSubTreeArray'] = $value;
    }

    protected function convertFacets($value)
    {
        $facets = array();
        $avoidDuplicateNameList = array();
        foreach ($value as $item) {

            $item = self::parseFacetQueryValue( $item, $this->solrNamesHelper );

            switch( $item['field'] )
            {
                case 'author':
                    {
                        $fields = array( \eZSolr::getMetaFieldName( 'owner_id', 'facet' ) );
                    } break;

                case 'class':
                    {
                        $fields = array( \eZSolr::getMetaFieldName( 'class_identifier', 'facet' ) );
                    } break;

//                case 'installation':
//                {
//                    $fields = array( \eZSolr::getMetaFieldName( 'installation_id', 'facet' ) );
//                } break;

                case 'translation':
                    {
                        $fields = array( \eZSolr::getMetaFieldName( 'language_code', 'facet' ) );
                    } break;

                default:
                    {
                        $fields = $this->solrNamesHelper->generateFieldNames($item['field'], 'filter');
                    } break;
            }

            $name = $item['field'];
            if (isset($avoidDuplicateNameList[$name])){
                $avoidDuplicateNameList[$name]++;
            }else{
                $avoidDuplicateNameList[$name] = 0;
            }
            if ($avoidDuplicateNameList[$name] > 0){
                $name .= $avoidDuplicateNameList[$name];
            }

            if ($item['query']){                
                list($queryField, $queryValue) = explode(':', $item['query']);
                $fieldNames = $this->solrNamesHelper->generateFieldNames( $queryField, 'filter' );
                $value = $this->cleanValue($queryValue);                            
                if (!is_array($queryValue) && count($fieldNames) > 0){
                    $fieldName = array_shift($fieldNames);
                    $item['query'] = $fieldName . ':' . $queryValue;
                }
            }

            foreach ($fields as $field) {
                $facets[] = array(
                    'field' => $field,
                    'name'=> $name,
                    'limit' => $item['limit'],
                    'offset' => $item['offset'],
                    'sort' => $item['sort'],
                    'query'  => $item['query'],
                    // 'range' => array(
                    //     'field' => 'published',
                    //     'start' => trim($this->formatFilterValue( (new \DateTime())->sub(new \DateInterval("P10Y"))->format(\DateTime::ISO8601), 'date' ), '"'),
                    //     'end' => 'NOW',
                    //     'gap' => '+1DAY'
                    // )
                );
            }
        }
        $this->convertedQuery['Facet'] = $facets;
    }

    public static function parseFacetQueryValue( $item, $solrNamesHelper = null)
    {
        $item = trim( $item, "'" );
        $parts = explode( '|', $item );
        
        $field = isset($parts[0]) ? $parts[0] : null;
        $sort = isset($parts[1]) ? $parts[1] : 'count';
        $limit = isset($parts[2]) ? $parts[2] : 100;
        $offset = isset($parts[3]) ? $parts[3] : 0;
        $query = isset($parts[4]) && strpos($parts[4], ':') !== false ? $parts[4] : null;

        return array(
            'field'=> $field,
            'limit' => $limit,
            'offset' => $offset,
            'sort' => $sort,
            'query' => $query
        );
    }

    protected function convertSelect($value)
    {
        if (!is_array($value)) {
            $value = array($value);
        }

        $this->convertedQuery['_filterFields'] = $value;
    }

    protected function convertLanguage($value)
    {
        if (!is_array($value)) {
            $value = array($value);
        }
        $value = array_map(function($value){
            $value = trim($value, "''");
            return trim($value);
        }, $value);

        $this->convertedQuery['_filterLanguages'] = $value;
    }

    protected function convertExtendedAttributeFilter(eZFindExtendedAttributeFilterInterface $attributeFilter, $key, $value)
    {
        $extendedFilter = array(
            'id' => $key,
            'params' => $value
        );
        if (!isset( $this->convertedQuery['ExtendedAttributeFilter'] )) {
            $this->convertedQuery['ExtendedAttributeFilter'] = array();
        }
        $this->convertedQuery['ExtendedAttributeFilter'][] = $extendedFilter;
    }

}
