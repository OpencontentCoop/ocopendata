<?php

namespace Opencontent\Opendata\Api;
use Opencontent\QueryLanguage\QueryBuilderInterface;

interface SearchResultDecoratorQueryBuilderAware
{
    public function setQueryBuilder(QueryBuilderInterface $builder);
}