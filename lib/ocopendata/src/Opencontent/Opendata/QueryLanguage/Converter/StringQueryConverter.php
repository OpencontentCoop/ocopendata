<?php

namespace Opencontent\QueryLanguage\Converter;

use Opencontent\QueryLanguage\Query;
use Opencontent\QueryLanguage\Parser\Item;

class StringQueryConverter implements QueryConverter
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
                $this->convertedQuery[] = $this->parseItem($item);
            }

            foreach ($this->query->getParameters() as $parameters) {
                $this->convertedQuery[] = $this->parseItem($parameters);
            }
        }

        return implode(self::SEPARATOR, $this->convertedQuery);
    }

    protected function parseItem(Item $item)
    {
        $query = '';
        if ($item->hasSentences()) {
            $queryItems = array();
            foreach ($item->getSentences() as $sentence) {
                $queryItems[] = (string)$sentence;
            }
            if ($item->hasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $queryItems[] = '( ' . $this->parseItem($child) . ' )';
                }
            }

            $separator = self::SEPARATOR;
            if ((string)$item->clause != '') {
                $separator = self::SEPARATOR . (string)$item->clause . self::SEPARATOR;
            }

            $query = implode($separator, $queryItems);
        }

        return $query;
    }
}
