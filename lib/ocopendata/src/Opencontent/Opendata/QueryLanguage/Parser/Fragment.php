<?php

namespace Opencontent\QueryLanguage\Parser;

use ArrayAccess;
use Iterator;
use Countable;

class Fragment implements ArrayAccess, Iterator, Countable
{
    const SEPARATOR = ' ';

    private $position = 0;

    protected $string;

    /**
     * @var Token[]
     */
    protected $tokens = array();

    /**
     * @var TokenFactory
     */
    protected $tokenFactory;

    public function __construct( $string = '', TokenFactory $tokenFactory )
    {
        $this->tokenFactory = $tokenFactory;
        $this->string = $string;
        $this->position = 0;
        $tokenStrings = explode( self::SEPARATOR, $string );
        $lastToken = null;
        foreach ( $tokenStrings as $tokenString )
        {
            $tokenString = trim( $tokenString );
            if ( $tokenString != '' )
            {
                $token = $this->tokenFactory->createQueryToken( $tokenString, $lastToken );
                if ( $token instanceof Token )
                {
                    $this->tokens[] = $token;
                    $lastToken = $token;
                }
            }
        }
    }

    public static function fromArray( $tokens, TokenFactory $tokenFactory )
    {
        $fragment = new Fragment( '', $tokenFactory );
        $fragment->tokens = array_values( $tokens );
        $fragment->rewind();
        return $fragment;
    }

    public function add( Token $token )
    {
        $tokens = $this->tokens;
        $tokens[] = $token;
        $this->tokens = array_values( $tokens );
        $this->rewind();
    }

    public function __toString()
    {
        return (string)implode( ' ', $this->tokens );
    }

    public function isValid()
    {
        return count( $this->tokens ) > 0;
    }

    /**
     * @return Token
     */
    public function first()
    {
        $tokens = $this->tokens;

        return array_shift( $tokens );
    }

    public function removeFirst()
    {
        $tokens = $this->tokens;
        unset( $tokens[0] );
        $this->tokens = array_values( $tokens );
        $this->rewind();
    }

    /**
     * @return Token
     */
    public function last()
    {
        $tokens = $this->tokens;
        return array_pop( $tokens );
    }

    public function removeLast()
    {
        $tokens = $this->tokens;
        $offset = $this->count() - 1;
        unset( $tokens[$offset] );
        $this->tokens = array_values( $tokens );
        $this->rewind();
    }

    public function offsetExists( $offset )
    {
        return isset( $this->tokens[$offset] );
    }

    public function offsetGet( $offset )
    {
        return $this->tokens[$offset];
    }

    public function offsetSet( $offset, $value )
    {
        $this->tokens[$offset] = $value;
    }

    public function offsetUnset( $offset )
    {
        $tokens = $this->tokens;
        unset( $tokens[$offset] );
        $this->tokens = array_values( $tokens );
        $this->rewind();
    }

    function rewind()
    {
        $this->position = 0;
        $this->string = $this->__toString();
    }

    function current()
    {
        return $this->tokens[$this->position];
    }

    function key()
    {
        return $this->position;
    }

    function next()
    {
        ++$this->position;
    }

    function valid()
    {
        return isset( $this->tokens[$this->position] );
    }

    public function count()
    {
        return count( $this->tokens );
    }

    public function slice( $offset, $length = null )
    {
        $tokens = $this->tokens;
        return array_slice( $tokens, $offset, $length );
    }

    public function chunk( $size )
    {
        $tokens = $this->tokens;
        return  array_chunk( $tokens, $size );
    }
}