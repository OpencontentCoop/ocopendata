<?php

namespace Opencontent\QueryLanguage\Parser;

class ArrayValue extends Value
{
    protected $data = array();

    private $pendingKey;

    public function __construct($data = array())
    {
        parent::__construct($data);
    }

    public function append($key, $value)
    {
        $key = trim($key);
        $value = is_string($value) ? trim($value) : $value;

        $isEmptyValue = Value::isEmpty($value);

        if ($key && !$isEmptyValue) {
            $this->data[$key] = $value;
        }
        if ($key && $isEmptyValue) {
            $this->pendingKey = $key;
        }

        if (!$key && !$isEmptyValue) {
            if ($this->pendingKey) {
                $this->data[$this->pendingKey] = $value;
                $this->pendingKey = null;
            } else {
                $this->data[] = $value;
            }
        }
    }

    public function toArray()
    {
        return $this->data;
    }

    public function __toString()
    {
        $value = $this->data;

        if (is_array($value)) {
            if (array_keys($value) === range(0, count($value) - 1)) {
                $valueArray = array();
                foreach ($value as $item){
                    if (is_array($item)){
                        $arrayValue = new ArrayValue($item);
                        $valueArray[] = $arrayValue;
                    }elseif (is_string($item)){
                        $valueArray[] = $item;
                    }
                }
                $string = '[' . implode(',', $valueArray) . ']';
            } else {
                $valueArray = array();
                foreach ($value as $key => $item) {
                    if (is_array($item)){
                        $arrayValue = new ArrayValue($item);
                        $valueArray[] = $key . '=>' . $arrayValue;
                    }elseif (is_string($item)){
                        $valueArray[] = $key . '=>' . $item;
                    }

                }
                $string = '[' . implode(',', $valueArray) . ']';
            }
        } else {
            $string = (string)$value;
        }

        return $string;
    }
}
