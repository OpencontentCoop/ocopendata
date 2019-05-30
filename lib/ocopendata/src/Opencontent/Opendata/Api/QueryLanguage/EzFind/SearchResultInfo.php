<?php

namespace Opencontent\Opendata\Api\QueryLanguage\EzFind;

use ezfSearchResultInfo;

class SearchResultInfo extends ezfSearchResultInfo
{
    public static function fromEzfSearchResultInfo($info)
    {
        return new self($info->ResultArray);
    }

    public function attributes()
    {
        $attributes = parent::attributes();
        $attributes[] = 'result';

        return $attributes;
    }

    public function attribute($attr)
    {
        if ($attr == 'result') {

            return $this->ResultArray;
        }

        return parent::attribute($attr);
    }
}
