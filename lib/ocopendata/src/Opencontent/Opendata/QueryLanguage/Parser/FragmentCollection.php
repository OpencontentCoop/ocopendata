<?php

namespace Opencontent\QueryLanguage\Parser;

use Iterator;
use Countable;

class FragmentCollection implements Iterator, Countable
{
    private $position = 0;

    protected $fragments = array();

    protected $fragmentParameters = array();

    /**
     * @var TokenFactory
     */
    protected $tokenFactory;

    public function __construct( TokenFactory $tokenFactory )
    {
        $this->tokenFactory = $tokenFactory;
    }

    /**
     * @param Fragment|FragmentCollection $fragment
     */
    public function add( $fragment )
    {
        if ( $fragment->isValid() )
        {
            if ( $fragment instanceof Fragment )
                $this->filterFragment( $fragment );
            else
                $this->filterFragmentCollection( $fragment );
        }
    }

    public function isValid()
    {
        $currentClause = null;
        foreach( $this->fragments as $item )
        {
            if ( $item instanceof Clause )
            {
                if ( $currentClause === null )
                {
                    $currentClause = (string)$item;
                }
                elseif( $currentClause != (string)$item )
                {
                    throw new Exception( "Syntax error ambiguous clause in \"{$this}\"" );
                }
            }
        }
        return $this->count() > 0;
    }


    protected function filterFragmentCollection( FragmentCollection $collection )
    {
        $this->fragments[] = $collection;
    }

    protected function filterFragment( Fragment $fragment )
    {
        if ( $fragment->count() > 1 )
        {
            $first = $fragment->first();
            if ( $first instanceof Token )
            {
                if ( $first->isClause() )
                {
                    $fragment->removeFirst();
                    $this->filterToken( $first );
                    $this->filterFragment( $fragment );

                    return;
                }
                elseif( !$first->isField() && !$first->isParameter() )
                {
                    throw new Exception( "Syntax error \"{$fragment}\": first token is not a field nor parameter" );
                }
            }

            $last = $fragment->last();
            if ( $last instanceof Token && $last->isClause() )
            {
                $fragment->removeLast();
                $this->filterFragment( $fragment );
                $this->filterToken( $last );
                return;
            }

            $buffer = 0;
            foreach( $fragment as $index => $token )
            {
                if ( $token->isClause())
                {
                    $this->filterFragment( Fragment::fromArray( $fragment->slice( $buffer, $index ), $this->tokenFactory ) );
                    $this->filterToken( $token );
                    $slice = Fragment::fromArray( $fragment->slice( ++$index, $fragment->count() ), $this->tokenFactory );
                    $this->filterFragment( $slice );
                    return;
                }
                if ( $token->isParameter() && (string) $first != (string) $token )
                {
                    $this->filterFragment( Fragment::fromArray( $fragment->slice( $buffer, $index ), $this->tokenFactory ) );
                    $slice = Fragment::fromArray( $fragment->slice( $index, $fragment->count() ), $this->tokenFactory );
                    $this->filterFragment( $slice );
                    return;
                }
            }

            if ( $fragment->count() == 3 )
            {
                $sentence = new Sentence();
                foreach( $fragment as $token )
                {
                    if ( $token->isField() )
                        $sentence->setField( $token );

                    if ( $token->isOperator() )
                        $sentence->setOperator( $token );

                    if ( $token->isValue() )
                        $sentence->setValue( $token );

                }
                if ( !$sentence->isValid() )
                    throw new Exception( "Syntax error in sentence \"{$fragment}\"" );
                $this->fragments[] = $sentence;
            }
            elseif ( $fragment->count() == 2 )
            {
                $parameter = new Parameter();
                foreach( $fragment as $token )
                {
                    if ( $token->isParameter() )
                        $parameter->setKey( $token );

                    if ( $token->isValue() )
                        $parameter->setValue( $token );
                }
                if ( !$parameter->isValid() )
                {
                    throw new Exception( "Syntax error in parameter \"{$fragment}\"" );
                }
                $this->fragmentParameters[] = $parameter;
            }
            else
                throw new Exception( "Syntax error fragment in \"{$fragment}\"" );
        }
        elseif ( $fragment->count() == 1 )
        {
            $this->filterToken( $fragment->last() );
        }
        else
            throw new Exception( "Syntax error fragment in \"{$fragment}\"" );
    }

    protected function filterToken( Token $token )
    {
        if ( $token->isClause() )
        {
            $this->fragments[] = new Clause( (string)$token );
        }
    }

    public function addRaw( $data )
    {
        if ( is_string( $data ) )
        {
            $this->add( new Fragment( $data, $this->tokenFactory ) );
        }
        else
        {
            $collection = new FragmentCollection( $this->tokenFactory );
            foreach( $data as $item )
                $collection->addRaw( $item );
            $this->add( $collection );
        }
    }

    public function count()
    {
        return count( $this->fragments );
    }

    public function getFragments()
    {
        return self::fromArray( $this->fragments, $this->tokenFactory, $this->fragmentParameters );
    }

    public function getFragmentParameters()
    {
        foreach( $this->fragments as $fragment )
        {
            if ( $fragment instanceof FragmentCollection )
                $this->fragmentParameters = array_merge( $this->fragmentParameters, $fragment->getFragmentParameters() );
        }
        return $this->fragmentParameters;
    }

    public static function fromArray( $fragments, TokenFactory $tokenFactory, $fragmentParameters = array() )
    {
        $instance = new FragmentCollection( $tokenFactory );
        $instance->fragments = $fragments;
        $instance->fragmentParameters = $fragmentParameters;
        return $instance;
    }

    public function __toString()
    {
        $stringArray = array();
        foreach( $this->fragments as $fragment )
        {
            $stringArray[] = (string)$fragment;
        }
        return '( ' . implode( ' ', $stringArray ) . ' )';
    }

    public function current()
    {
        return $this->fragments[$this->position];
    }

    public function next()
    {
        ++$this->position;
    }

    public function key()
    {
        return $this->position;
    }

    public function valid()
    {
        return isset( $this->fragments[$this->position] );
    }

    public function rewind()
    {
        $this->position = 0;
    }
}