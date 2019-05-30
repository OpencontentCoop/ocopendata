<?php

namespace Opencontent\Opendata\Api;

use Opencontent\Opendata\Api\Values\SearchResults;

interface SearchResultDecoratorInterface
{
    public function decorate(SearchResults $searchResults, $rawResults);
}