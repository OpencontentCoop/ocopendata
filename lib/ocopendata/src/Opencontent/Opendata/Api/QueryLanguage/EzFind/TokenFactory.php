<?php

namespace Opencontent\Opendata\Api\QueryLanguage\EzFind;

use Opencontent\QueryLanguage\Parser\Token;
use Opencontent\QueryLanguage\Parser\TokenFactory as BaseTokenFactory;

class TokenFactory extends BaseTokenFactory
{
    protected $functionFields = array();

    protected $metaFields = array();

    protected $customSubFields = array();

    protected function isField(Token $token)
    {
        return $this->findFieldType($token);
    }

    protected function findFieldType(Token $token)
    {
        $string = (string)$token;

        foreach ($this->functionFields as $functionField) {
            if (strpos($string . '[', $functionField) === 0) {
                $token->data('is_function_field', true);
                $token->data('function', $functionField);

                return true;
            }
        }

        if (in_array($string, $this->metaFields)) {
            $token->data('is_meta_field', true);

            return true;

        } elseif (in_array($string, $this->fields)) {
            $token->data('is_field', true);

            return true;

        } else {
            $subParts = explode('.', $string);
            if (count($subParts) > 1) {
                $subTokens = array();
                foreach ($subParts as $part) {
                    $tokenPart = $this->createQueryToken($part);
                    if (!$this->isField($tokenPart)
                        && !in_array((string)$tokenPart, array_keys($this->customSubFields))
                    ) {

                        return false;

                    } else {
                        if (in_array((string)$tokenPart, array_keys($this->customSubFields))) {
                            $tokenPart->data('is_custom_subfield', true);
                            $tokenPart->data('custom_subfield_type', $this->customSubFields[(string)$tokenPart]);
                        }
                        $subTokens[] = $tokenPart;
                    }
                }
                $token->data('is_field', true);
                $token->data('sub_fields', $subTokens);

                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function getFunctionFields()
    {
        return $this->functionFields;
    }

    /**
     * @param array $functionFields
     *
     * @return TokenFactory
     */
    public function setFunctionFields($functionFields)
    {
        $this->functionFields = $functionFields;

        return $this;
    }

    /**
     * @return array
     */
    public function getMetaFields()
    {
        return $this->metaFields;
    }

    /**
     * @param array $metaFields
     *
     * @return TokenFactory
     */
    public function setMetaFields($metaFields)
    {
        $this->metaFields = $metaFields;

        return $this;
    }

    /**
     * @return array
     */
    public function getCustomSubFields()
    {
        return $this->customSubFields;
    }

    /**
     * @param array $customSubFields
     *
     * @return TokenFactory
     */
    public function setCustomSubFields($customSubFields)
    {
        $this->customSubFields = $customSubFields;

        return $this;
    }
}
