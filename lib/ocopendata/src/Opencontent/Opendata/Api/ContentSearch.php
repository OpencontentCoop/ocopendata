<?php

namespace Opencontent\Opendata\Api;

use Opencontent\Opendata\Api\QueryLanguage;
use Opencontent\QueryLanguage\QueryBuilderInterface;

class ContentSearch
{
    /**
     * @var string
     */
    private $query;

    /**
     * @var EnvironmentSettings
     */
    private $environmentSettings;

    /**
     * @var QueryBuilderInterface
     */
    private $queryBuilder;

    /**
     * @var SearchGateway
     */
    private $searchGateway;

    public function __construct()
    {
        $this->queryBuilder = new QueryLanguage\EzFind\QueryBuilder();
        $this->searchGateway = new QueryLanguage\EzFind\SearchGateway();
    }

    public function search($query, array $limitation = null)
    {
        $this->query = $query;
        $this->searchGateway->setEnvironmentSettings($this->environmentSettings);
        $this->searchGateway->setQueryBuilder($this->queryBuilder);

        return $this->searchGateway->search($query, $limitation);
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param $query
     *
     * @return $this
     */
    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @return EnvironmentSettings
     */
    public function getEnvironmentSettings()
    {
        return $this->environmentSettings;
    }

    /**
     * Alias of method getEnvironmentSettings
     *
     * @return EnvironmentSettings
     */
    public function getCurrentEnvironmentSettings()
    {
        return $this->environmentSettings;
    }

    /**
     * @param EnvironmentSettings $environmentSettings
     *
     * @return $this
     */
    public function setEnvironment(EnvironmentSettings $environmentSettings)
    {
        $this->environmentSettings = $environmentSettings;

        return $this;
    }

    /**
     * Alias of method setEnvironment
     *
     * @param EnvironmentSettings $environmentSettings
     *
     * @return $this
     */
    public function setCurrentEnvironmentSettings(EnvironmentSettings $environmentSettings)
    {
        $this->environmentSettings = $environmentSettings;

        return $this;
    }

    /**
     * @return QueryBuilderInterface
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * @param QueryBuilderInterface $queryBuilder
     *
     * @return ContentSearch
     */
    public function setQueryBuilder(QueryBuilderInterface $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;

        return $this;
    }
}
