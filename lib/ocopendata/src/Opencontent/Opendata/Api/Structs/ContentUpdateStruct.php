<?php

namespace Opencontent\Opendata\Api\Structs;


class ContentUpdateStruct extends ContentCreateStruct
{
    public function validate($ignorePolicies = false)
    {
        $this->metadata->validateOnUpdate();
        if ($ignorePolicies === false) {
            $this->metadata->checkAccess();
        }
        if ($this->options->isUpdateNullFields()){
            $this->data->validateOnCreate( $this->metadata, $this->options );
        }else{
            $this->data->validateOnUpdate( $this->metadata, $this->options );
        }
    }
}
