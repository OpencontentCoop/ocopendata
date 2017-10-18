<?php

interface OcOpenDataClientInterface
{

    public function pushDataset( $data );

    public function deleteDataset( $dataset, $purge = false );

    public function getDataset( $datasetId );

    public function pushOrganization( $data );

    public function deleteOrganization( $data, $purge = false );

    public function getOrganization( $organizationId );

    public function getLicenseList();
}