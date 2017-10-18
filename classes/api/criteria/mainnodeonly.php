<?php

class ezpContentMainNodeOnlyCriteria implements ezpContentCriteriaInterface
{
    private $mainNodeOnly;

    public function __construct( $mainNodeOnly )
    {
        $this->mainNodeOnly = (boolean)$mainNodeOnly;
    }

    public function translate()
    {
        return array(
            'type'      => 'param',
            'name'      => array( 'MainNodeOnly' ),
            'value'     => array( $this->mainNodeOnly )
        );
    }

    public function __toString()
    {
        return 'With mainNodeOnly '.$this->mainNodeOnly;
    }
}
?>