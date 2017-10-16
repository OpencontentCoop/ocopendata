<?php

namespace Opencontent\QueryLanguage\Converter;

use Opencontent\QueryLanguage\Parser\Parameter;
use Opencontent\QueryLanguage\Query;
use Opencontent\QueryLanguage\Parser\Item;

class AnalyzerQueryConverter implements QueryConverter
{

    const SEPARATOR = ' ';

    /**
     * @var Query
     */
    protected $query;

    /**
     * @var array
     */
    protected $convertedQuery = array();

    public function setQuery(Query $query)
    {
        $this->query = $query;
    }

    public function convert()
    {
        if ($this->query instanceof Query) {
            foreach ($this->query->getFilters() as $item) {
                $this->parseItem($item);
            }

            foreach ($this->query->getParameters() as $parameters) {
                $this->parseItem($parameters);
            }
        }

        return $this->convertedQuery;
    }

    protected function parseItem(Item $item)
    {
        if ($item->hasSentences()) {
            $convertedItems = array();
            $clause = false;
            if ((string)$item->clause !== '') {
                $clause = array(
                    'type' => 'clause',
                    'value' => (string)$item->clause
                );
            }

            foreach ($item->getSentences() as $sentence) {
                if ($sentence instanceof Parameter) {
                    $convertedItems[] = array(
                        'type' => 'parameter',
                        'key' => (string)$sentence->getKey(),
                        'key_data' => $sentence->getKey()->data(),
                        'value' => $sentence->stringValue(),
                        'format' => is_array($sentence->getValue()) ? 'array' : 'string',
                        '_toString' => (string)$sentence
                    );
                } else {
                    $convertedItems[] = array(
                        'type' => 'filter',
                        'field' => (string)$sentence->getField(),
                        'field_data' => $sentence->getField()->data(),
                        'operator' => (string)$sentence->getOperator(),
                        'value' => $sentence->stringValue(),
                        'format' => is_array($sentence->getValue()) ? 'array' : 'string',
                        '_toString' => (string)$sentence
                    );
                }
            }

            if ($item->hasChildren()) {
                $convertedItems[] = array(
                    'type' => 'parenthesis',
                    'value' => '('
                );
                $countConvertedItems = count($convertedItems);
                foreach ($convertedItems as $index => $convertedItem) {
                    $this->convertedQuery[] = $convertedItem;
                    if (is_array($clause) && ( $index + 1 ) < $countConvertedItems) {
                        $this->convertedQuery[] = $clause;
                    }
                }
                foreach ($item->getChildren() as $child) {
                    $this->parseItem($child);
                }
                $this->convertedQuery[] = array(
                    'type' => 'parenthesis',
                    'value' => ')'
                );
            } else {
                $countConvertedItems = count($convertedItems);
                foreach ($convertedItems as $index => $convertedItem) {
                    $this->convertedQuery[] = $convertedItem;
                    if (is_array($clause) && ( $index + 1 ) < $countConvertedItems) {
                        $this->convertedQuery[] = $clause;
                    }
                }
            }
        }
    }
}
