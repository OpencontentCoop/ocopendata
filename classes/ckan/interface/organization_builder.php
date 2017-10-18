<?php

interface OcOpendataOrganizationBuilderInterface
{
    /**
     * @return \Opencontent\Ckan\DatiTrentinoIt\Organization
     */
    public function build();

    public function storeOrganizationPushedId($returnData);

    public function removeStoresOrganizationId();

    public function getStoresOrganizationId();
}