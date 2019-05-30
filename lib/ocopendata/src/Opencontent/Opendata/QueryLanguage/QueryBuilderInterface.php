<?php

namespace Opencontent\QueryLanguage;


interface QueryBuilderInterface
{
    /**
     * @param $string
     * @return Query
     */
    public function instanceQuery($string);

    /**
     * @return Parser\TokenFactory
     */
    public function getTokenFactory();


    /**
     * @return Converter\QueryConverter;
     */
    public function getConverter();
}