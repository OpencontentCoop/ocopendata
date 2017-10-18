<?php

namespace Opencontent\QueryLanguage\Converter;

use Opencontent\QueryLanguage\Query;

interface QueryConverter
{
    /**
     * @param Query $query
     *
     * @return mixed
     */
    public function setQuery( Query $query );

    /**
     * @return mixed
     */
    public function convert();
}