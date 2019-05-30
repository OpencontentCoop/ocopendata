<?php

namespace Opencontent\Opendata\Api;

use Opencontent\Opendata\Api\Values\SearchResults;
use Opencontent\Opendata\Api\QueryLanguage;
use Opencontent\QueryLanguage\QueryBuilderInterface;
use Opencontent\Opendata\Api\EnvironmentSettings;

interface SearchGateway
{
    /**
     * @param mixed $query
     * @param mixed|null $limitation
     * @return SearchResults|mixed
     */
    public function search($query, $limitation = null);

    /**
     * @param EnvironmentSettings $environmentSettings
     */
    public function setEnvironmentSettings(EnvironmentSettings $environmentSettings);

    /**
     * @return EnvironmentSettings
     */
    public function getEnvironmentSettings();

    /**
     * @param QueryBuilderInterface $queryBuilder
     */
    public function setQueryBuilder(QueryBuilderInterface $queryBuilder);

    /**
     * @return QueryBuilderInterface
     */
    public function getQueryBuilder();
}
