<?php

namespace Opencontent\QueryLanguage\Parser;

class Sentence
{
    /**
     * @var Token
     */
    protected $field;

    /**
     * @var Token[]
     */
    protected $operator = array();

    /**
     * @var Value
     */
    protected $value;

    public function getField()
    {
        return $this->field;
    }

    public function getOperator()
    {
        return implode(' ', $this->operator);
    }

    public function getValue()
    {
        return $this->value instanceof ArrayValue ?
            $this->value->toArray() :
            (string) $this->value;
    }

    public function setField(Token $data)
    {
        $this->field = $data;
    }

    public function setOperator(Token $data)
    {
        $this->operator[] = $data;
    }

    public function setValue(Token $data)
    {
        $this->value = ValueParser::parseString($data);
    }

    public function __toString()
    {
        return $this->getField() . ' ' . $this->getOperator() . ' ' . $this->stringValue();
    }

    public function isValid()
    {
        return $this->field !== null && !empty($this->operator) && !empty($this->stringValue());
    }

    public function stringValue()
    {
        return (string)$this->value;
    }

    /**
     * @param $variableValue
     * @return string|Value
     */
    public static function parseString($variableValue)
    {
        $value = ValueParser::parseString($variableValue);

        if ($value instanceof ArrayValue){
            return $value->toArray();
        }

        return (string)$value;
    }
}
