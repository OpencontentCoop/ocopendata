<?php

namespace Opencontent\Opendata\Api\Structs;


class ContentUpdateStruct extends ContentCreateStruct
{
    public function validate()
    {
        $this->metadata->validateOnUpdate();
        $this->data->validateOnUpdate( $this->metadata );
    }
}