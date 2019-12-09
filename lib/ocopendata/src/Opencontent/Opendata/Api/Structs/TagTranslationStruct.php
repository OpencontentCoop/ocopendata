<?php

namespace Opencontent\Opendata\Api\Structs;


class TagTranslationStruct
{
    public $tagId;

    public $keyword;

    public $locale;

    public $alwaysAvailable = true;

    public $isMainTranslation = false;
}