<?php

namespace Opencontent\Opendata\Api\QueryLanguage\EzFind;

use Opencontent\Opendata\Api\ClassRepository;
use Opencontent\QueryLanguage\QueryBuilder as BaseQueryBuilder;


class QueryBuilder extends BaseQueryBuilder
{
    protected $fields = array(
        'q'
    );

    protected $metaFields = array(
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

    protected $parameters = array(
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

    protected $operators = array(
        '=',
        '!=',
        'in',
        '!in',
        'contains',
        '!contains',
        'range',
        '!range'
    );

    protected $functionFields = array(
        'calendar',
        'raw'
    );

    protected $customSubFields = array(
        'tag_ids' => 'sint'
    );

    protected $solrNamesHelper;

    public function __construct()
    {
        $classRepository = new ClassRepository();
        $availableFieldDefinitions = $classRepository->listAttributesGroupedByIdentifier();

        $this->fields = array_merge(
            $this->fields,
            array_keys($availableFieldDefinitions)
        );

        $this->tokenFactory = new TokenFactory();
        $this->tokenFactory->setFunctionFields($this->functionFields)
                           ->setMetaFields($this->metaFields)
                           ->setCustomSubFields($this->customSubFields)
                           ->setFields($this->fields)
                           ->setOperators($this->operators)
                           ->setParameters($this->parameters)
                           ->setClauses($this->clauses);

        $this->solrNamesHelper = new SolrNamesHelper($availableFieldDefinitions, $this->tokenFactory);

        $sentenceConverter = new SentenceConverter($this->solrNamesHelper);

        $parameterConverter = new ParameterConverter($this->solrNamesHelper);

        $this->converter = new QueryConverter(
            $sentenceConverter,
            $parameterConverter
        );
    }

    public function instanceQuery($string)
    {
        $this->solrNamesHelper->reset();

        return parent::instanceQuery($string);
    }

    public function getSolrNamesHelper()
    {
        return $this->solrNamesHelper;
    }

    /**
     * @return array
     */
    public function getFunctionFields()
    {
        return $this->functionFields;
    }

    /**
     * @param array $functionFields
     */
    public function setFunctionFields($functionFields)
    {
        $this->functionFields = $functionFields;
    }

    /**
     * @return array
     */
    public function getCustomSubFields()
    {
        return $this->customSubFields;
    }

    /**
     * @param array $customSubFields
     */
    public function setCustomSubFields($customSubFields)
    {
        $this->customSubFields = $customSubFields;
    }

}
