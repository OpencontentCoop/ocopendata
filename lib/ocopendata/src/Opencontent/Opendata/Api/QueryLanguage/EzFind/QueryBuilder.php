<?php

namespace Opencontent\Opendata\Api\QueryLanguage\EzFind;

use Opencontent\Opendata\Api\ClassRepository;
use Opencontent\QueryLanguage\QueryBuilder as BaseQueryBuilder;
use eZINI;

class QueryBuilder extends BaseQueryBuilder
{
    public $fields = array(
        'q',
        'ez_tag_ids',
        'ez_all_texts'
    );

    public $metaFields = array(
        'id',
        'remote_id',
        'name',
        'published',
        'modified',
        'section',
        'state',
        'class',
        'owner_id'
    );

    public $parameters = array(
        'sort',
        'geosort',
        'limit',
        'offset',
        'classes',
        'subtree',
        'facets',
        'select-fields',
        'language'
    );

    public $operators = array(
        '=',
        '!=',
        'in',
        '!in',
        'contains',
        '!contains',
        'range',
        '!range'
    );

    public $functionFields = array(
        'calendar',
        'raw'
    );

    protected $solrNamesHelper;

    public function __construct()
    {
        $classRepository = new ClassRepository();
        $availableFieldDefinitions = $classRepository->listAttributesGroupedByIdentifier();

//        echo '<pre>';
//        print_r( $availableFieldDefinitions );
//        die();

        $this->fields = array_merge(
            $this->fields,
            array_keys( $availableFieldDefinitions )
        );
        
        $filtersList = (array)eZINI::instance( 'ezfind.ini' )->variable( 'ExtendedAttributeFilters', 'FiltersList' );
        $this->parameters = array_merge($this->parameters, array_keys($filtersList));

        $this->tokenFactory = new TokenFactory(
            $this->fields,
            $this->metaFields,
            $this->functionFields,
            $this->operators,
            $this->parameters,
            $this->clauses
        );

        $this->solrNamesHelper = new SolrNamesHelper( $availableFieldDefinitions, $this->tokenFactory );

        $sentenceConverter = new SentenceConverter( $this->solrNamesHelper );

        $parameterConverter = new ParameterConverter( $this->solrNamesHelper );

        $this->converter = new QueryConverter(
            $sentenceConverter,
            $parameterConverter
        );
    }

    public function instanceQuery( $string )
    {
        $this->solrNamesHelper->reset();
        return parent::instanceQuery($string);
    }

    public function getSolrNamesHelper(): SolrNamesHelper
    {
        return $this->solrNamesHelper;
    }

}
