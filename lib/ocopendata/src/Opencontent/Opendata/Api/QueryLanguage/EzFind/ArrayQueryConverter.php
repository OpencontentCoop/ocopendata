<?php

namespace Opencontent\Opendata\Api\QueryLanguage\EzFind;

use Opencontent\QueryLanguage\Query;
use Opencontent\QueryLanguage\Converter\QueryConverter as QueryConverterInterface;
use Opencontent\QueryLanguage\Parser\Item;
use Opencontent\QueryLanguage\Parser\Parameter;

class ArrayQueryConverter implements QueryConverterInterface
{
    /**
     * @var Query
     */
    private $query;

    /**
     * @var array
     */
    private $convertedQuery = array();

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

    private function parseItem(Item $item)
    {
        if ($item->hasSentences()) {
            foreach ($item->getSentences() as $sentence) {

                $value = $sentence->getValue();

                if ($sentence instanceof Parameter) {
                    $field = (string)$sentence->getKey();
                    $this->convertedQuery[$field] = $this->cleanValue($value);
                } else {
                    $field = (string)$sentence->getField();
                    if (!isset( $this->convertedQuery['filter'] )) {
                        $this->convertedQuery['filter'] = array();
                    }
                    $this->convertedQuery['filter'][$field] = $this->cleanValue($value);
                }
            }
            if ($item->hasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $this->parseItem($child);
                }
            }
        }
    }

    private function cleanValue($value)
    {
        if (is_array($value)) {
            return array_map(array($this, 'cleanValue'), $value);
        } else {
            return trim($value, "'");
        }
    }
}
