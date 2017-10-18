<?php

interface OcOpendataDatasetGeneratorInterface
{
    /**
     * @param $classIdentifier
     * @param array $parameters
     * @param bool $dryRun
     *
     * @return mixed
     */
    public function createFromClassIdentifier( $classIdentifier, $parameters = array(), $dryRun = null);
}