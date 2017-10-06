<?php

namespace Opencontent\Opendata\Api\QueryLanguage\EzFind;

use Opencontent\QueryLanguage\Converter\QueryConverter as QueryConverterInterface;
use Opencontent\QueryLanguage\Query;
use Opencontent\QueryLanguage\Parser\Item;
use Opencontent\QueryLanguage\Parser\Parameter;
use ArrayObject;


class QueryConverter implements QueryConverterInterface
{
    /**
     * @var Query
     */
    protected $query;

    /**
     * @var ArrayObject
     */
    protected $convertedQuery;

    /**
     * @var SentenceConverter
     */
    protected $sentenceConverter;

    /**
     * @var ParameterConverter
     */
    protected $parameterConverter;

    public function __construct(
        SentenceConverter $sentenceConverter,
        ParameterConverter $parameterConverter
    ) {
        $this->parameterConverter = $parameterConverter;
        $this->sentenceConverter = $sentenceConverter;
    }

    public function setQuery(Query $query)
    {
        $this->query = $query;
    }

    /**
     * @return ArrayObject
     */
    public function convert()
    {
        if ($this->query instanceof Query) {
            $this->convertedQuery = new ArrayObject(
                array('_query' => null)
            );
            $this->parameterConverter->setCurrentConvertedQuery($this->convertedQuery);
            $this->sentenceConverter->setCurrentConvertedQuery($this->convertedQuery);

            $this->convertParameters();
            $this->convertFilters();

            if (isset( $this->convertedQuery['Filter'] ) && empty( $this->convertedQuery['Filter'] )) {
                unset( $this->convertedQuery['Filter'] );
            }
        }

        return $this->convertedQuery;
    }

    protected function convertFilters()
    {
        $filters = array();
        foreach ($this->query->getFilters() as $item) {
            $filter = $this->parseItem($item);
            if (!empty( $filter ) && $filter !== null) {
                if (is_array($filter) && count($filter) == 1) {
                    $filter = array_pop($filter);
                }
                $filters[] = $filter;
            }
        }
        if (!empty( $filters )) {
            $this->convertedQuery['Filter'] = $filters;
        }
    }

    protected function convertParameters()
    {
        foreach ($this->query->getParameters() as $parameters) {
            foreach ($parameters->getSentences() as $parameter) {
                if ($parameter instanceof Parameter) {
                    $this->parameterConverter->convert($parameter);
                }
            }
        }
    }

    protected function parseItem(Item $item)
    {
        $filters = array();
        if ($item->hasSentences() || $item->clause == 'or') {
            if ($item->clause == 'or') {
                $filters[] = (string)$item->clause;
            }

            foreach ($item->getSentences() as $sentence) {
                $result = $this->sentenceConverter->convert($sentence);
                if ($result !== null) {
                    $filters[] = $result;
                }
            }
            if ($item->hasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $filters[] = $this->parseItem($child);
                }
            }
        }

        return $filters;
    }

}
