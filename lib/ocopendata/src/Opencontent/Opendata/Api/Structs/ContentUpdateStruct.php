<?php

namespace Opencontent\Opendata\Api\Structs;


class ContentUpdateStruct extends ContentCreateStruct
{
    public function validate()
    {
        $this->metadata->validateOnUpdate();
        if ($this->options->attribute('update_null_field')){
            $this->data->validateOnCreate( $this->metadata, $this->options );
        }else{
            $this->data->validateOnUpdate( $this->metadata, $this->options );
        }
    }
}
