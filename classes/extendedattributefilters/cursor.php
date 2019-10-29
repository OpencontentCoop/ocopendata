<?php

use Opencontent\Opendata\Api\SearchResultDecoratorInterface;
use Opencontent\Opendata\Api\SearchResultDecoratorQueryBuilderAware;
use Opencontent\Opendata\Api\Values\SearchResults;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\SearchResultInfo;
use Opencontent\QueryLanguage\QueryBuilderInterface;
use Opencontent\QueryLanguage\Parser\Item;
use Opencontent\QueryLanguage\Converter\StringQueryConverter;
use Opencontent\QueryLanguage\Parser\Parameter;
use Opencontent\QueryLanguage\Parser\Token;

class OpendataCursorExtendedAttributeFilter implements eZFindExtendedAttributeFilterInterface, SearchResultDecoratorInterface, SearchResultDecoratorQueryBuilderAware
{
    private $currentCursor;

    /**
     * @var QueryBuilderInterface
     */
    private $queryBuilder;

    public function filterQueryParams(array $queryParams, array $filterParams)
    {
        $cursor = $filterParams[0];
        $queryParams['cursorMark'] = $cursor;
        $queryParams['sort'] .= trim($queryParams['sort']) == '' ? '' : ', ';
        $queryParams['sort'] .= ezfSolrDocumentFieldBase::generateMetaFieldName('guid') . ' asc';

        return $queryParams;
    }

    public function decorate(SearchResults $searchResults, $rawResults)
    {
        $searchExtra = SearchResultInfo::fromEzfSearchResultInfo($rawResults['SearchExtras']);
        $resultArray = $searchExtra->attribute('result');
        $resultCount = count($resultArray['response']['docs']);
        $limit = isset($resultArray['responseHeader']['params']['rows']) ? $resultArray['responseHeader']['params']['rows'] : false;
        $currentCursor = isset($resultArray['responseHeader']['params']['cursorMark']) ? $resultArray['responseHeader']['params']['cursorMark'] : false;
        if ($currentCursor) {
            $nextCursor = $resultArray['nextCursorMark'];

            $next = ($currentCursor == $nextCursor || $resultCount < $limit) ? null : $nextCursor;
            $currentQuery = $this->fixQuery($searchResults->query, $currentCursor);
            $nextQuery = $next ? $this->fixQuery($searchResults->nextPageQuery, $next) : null;

            $searchResults->query = $currentQuery;
            $searchResults->nextPageQuery = $nextQuery;
            $searchResults->currentCursor = $currentCursor;
            $searchResults->nextCursor = $next;
        }
    }

    public function setQueryBuilder(QueryBuilderInterface $builder)
    {
        $this->queryBuilder = $builder;
    }

    private function fixQuery($query, $cursor)
    {
        $currentQuery = $this->queryBuilder->instanceQuery($query);
        $currentQuery->parse();

        $convertedCurrentQuery = [];
        foreach ($currentQuery->getFilters() as $item) {
            $convertedCurrentQuery[] = $this->parseQueryItem($item, $cursor);
        }

        foreach ($currentQuery->getParameters() as $parameters) {
            $convertedCurrentQuery[] = $this->parseQueryItem($parameters, $cursor);
        }

        return implode(StringQueryConverter::SEPARATOR, $convertedCurrentQuery);
    }

    private function parseQueryItem(Item $item, $cursor)
    {
        $query = '';
        if ($item->hasSentences()) {
            $queryItems = array();
            foreach ($item->getSentences() as $sentence) {
                if ($sentence instanceof Parameter && $sentence->getKey() == 'offset') {
                    continue;
                }
                if ($sentence instanceof Parameter && $sentence->getKey() == 'cursor') {
                    if (!$cursor){
                        continue;
                    }else {
                        $token = new Token();
                        $token->setToken("[$cursor]");
                        $token->setType('value');
                        $sentence->setValue($token);
                    }
                }
                $queryItems[] = (string)$sentence;
            }
            if ($item->hasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $queryItems[] = '( ' . $this->parseQueryItem($child, $cursor) . ' )';
                }
            }

            $separator = StringQueryConverter::SEPARATOR;
            if ((string)$item->clause != '')
                $separator = StringQueryConverter::SEPARATOR . (string)$item->clause . StringQueryConverter::SEPARATOR;

            $query = implode($separator, $queryItems);
        }
        return $query;
    }


}