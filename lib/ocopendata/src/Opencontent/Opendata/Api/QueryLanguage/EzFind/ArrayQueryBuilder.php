<?php

namespace Opencontent\Opendata\Api\QueryLanguage\EzFind;

use Opencontent\QueryLanguage\Converter\Exception;

class ArrayQueryBuilder extends QueryBuilder
{
    private $normalized = array();

    public function instanceQuery($queryArray)
    {
        $string = $this->convertQueryToString($queryArray);

        return parent::instanceQuery($string);
    }

    private function convertQueryToString(array $queryArray)
    {
        foreach ($queryArray as $key => $value) {
            if ($key == 'filter') {
                $this->parseFilters($value);

            } elseif (in_array($key, $this->parameters)) {
                $this->normalized[] = $this->stringify($key, $value, false);
            }
        }

        if (empty($this->normalized)){
            throw new \Exception("Wrong parameters");
        }

        return implode( ' and ', $this->normalized);
    }

    private function stringify($field, $value, $isFilter = true)
    {
        $string = null;
        $operator = '=';
        if (is_string($value)) {
            $string = !$isFilter ? $value : "'$value'";
        } elseif (is_array($value) && !empty( $value )) {
            $stringParts = array();
            foreach ($value as $key => $item) {
                if (is_string($key) && is_string($item)) {
                    $stringParts[] = "$key=>$item";
                } else {
                    $stringParts[] = is_numeric($item) || !$isFilter ? $item : "'$item'";
                }
            }
            $operator = 'in';
            $string = '[' . implode(',', $stringParts) . ']';
        }
        if ($string) {
            if ($isFilter) {
                return "$field $operator $string";
            } else {
                return "$field $string";
            }
        }
        throw new \Exception("Wrong parameter ", var_export($value, 1));
    }

    private function parseFilters($data)
    {
        foreach ($data as $key => $value) {
            $this->parseFilter($key, $value);
        }
    }

    private function parseFilter($filterName, $filterValue)
    {
        $tokenPart = $this->tokenFactory->createQueryToken($filterName);
        if (!$tokenPart->isField()) {
            throw new \Exception("Wrong filter name \"$tokenPart\"");
        }
        $this->normalized[] = $this->stringify($filterName, $filterValue);
    }
}
