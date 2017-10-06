<?php

namespace Opencontent\QueryLanguage\Parser;

class Token
{
    protected $token;

    protected $type;

    protected $data = array();

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function __toString()
    {
        return $this->token;
    }

    public function isField()
    {
        return $this->type == 'field';
    }

    public function isOperator()
    {
        return $this->type == 'operator';
    }

    public function isClause()
    {
        return $this->type == 'clause';
    }

    public function isValue()
    {
        return $this->type == 'value';
    }

    public function isParameter()
    {
        return $this->type == 'parameter';
    }

    public function getType()
    {
        return $this->type;
    }

    public function isSameType(Token $token)
    {
        return $this->type == $token->getType();
    }

    public function append($string)
    {
        $this->token .= $string;
    }

    /**
     * @param null $key
     * @param null $value
     *
     * @return array|null
     */
    public function data($key = null, $value = null)
    {
        if ($key && $value === null) {
            return isset( $this->data[$key] ) ? $this->data[$key] : null;
        } elseif ($key && $value) {
            $this->data[$key] = $value;

            return null;
        } else {
            return $this->data;
        }
    }

    public function validateNextToken(Token $nextToken)
    {
        if ($this->type == $nextToken->getType() && $this->token != 'not') {
            throw new Exception("Duplicate field type on \"{$this} {$nextToken}\"");
        }

        if ($this->isClause() && $nextToken->isValue()) {
            throw new Exception("Clause in values non yet supported on \"{$this} {$nextToken}\"");
        }
    }

}
