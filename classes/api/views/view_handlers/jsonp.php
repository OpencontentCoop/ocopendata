<?php

class OCOpenDataJsonPViewHandler implements ezcMvcViewHandler
{

    /**
     * Contains the zone name
     *
     * @var string
     */
    protected $zoneName;

    /**
     * Contains the variables that will be available in the template.
     *
     * @var array(mixed)
     */
    protected $variables = array();

    /**
     * Contains the result after process() has been called.
     *
     * @var mixed
     */
    protected $result;

    public function __construct( $zoneName, $templateLocation = null )
    {
        $this->zoneName = $zoneName;
    }

    public function send( $name, $value )
    {
        $this->variables[$name] = $value;
    }

    public function process( $last )
    {
        if ( $last )
        {
            $callback = uniqid('callback');
            $text = null;
            if ( isset( $this->variables['callback'] ) )
            {
                $callback = $this->variables['callback'];
            }
            if ( isset( $this->variables['content'] ) )
            {
                $text = (string)$this->variables['content'];
            }
            $this->result = "/**/$callback($text)";
        }
    }

    public function getName()
    {
        return $this->zoneName;
    }

    public function getResult()
    {
        return $this->result;
    }
}
