<?php

namespace Opencontent\QueryLanguage\Parser;

class TokenFactory
{
    protected $fields;

    protected $operators;

    protected $parameters;

    protected $clauses;

    /**
     * @var ?callable
     */
    protected $subQueryResolver;

    /**
     * @param $string
     * @param Token|null $previousToken
     *
     * @return null|Token
     */
    public function createQueryToken( $string, Token $previousToken = null )
    {
        $token = new Token();
        $token->setToken( $string );

        $this->findType( $token );

        if ( $previousToken instanceof Token )
        {
            if ( $this->appendToPrevious( $token, $previousToken ) )
            {
                return null;
            }
        }

        return $token;
    }

    protected function findType( Token $token )
    {
        if ( $this->isField( $token ) )
        {
            $this->setIsField( $token );
        }
        elseif ( $this->isClause( $token ) )
        {
            $this->setIsClause( $token );
        }
        elseif ( $this->isParameter( $token ) )
        {
            $this->setIsParameter( $token );
        }
        elseif ( $this->isOperator( $token ) )
        {
            $this->setIsOperator( $token );
        }
        else
        {
            $token->setType( 'value' );
        }
    }

    /**
     * @param Token $token
     * @param Token $previousToken
     * @return bool if returns true the string will be appen to $previousToken
     */
    protected function appendToPrevious( Token $token, Token $previousToken )
    {
        if ( $token->isSameType( $previousToken ) && $token->isValue() )
        {
            $previousToken->append( Fragment::SEPARATOR . (string) $token );
            return true;
        }

        $lastQuoteEscapedCount = substr_count( (string)$previousToken, "\'" );
        $lastQuoteCount = substr_count( (string)$previousToken, "'" ) - $lastQuoteEscapedCount;
        if ( $lastQuoteCount % 2 != 0 )
        {
            $previousToken->append( Fragment::SEPARATOR . (string) $token );
            return true;
        }

        $lastBracketEscapedCount = substr_count( (string)$previousToken, "\[" );
        $lastBracketAltEscapedCount = substr_count( (string)$previousToken, "\]" );
        $lastBracketCount = substr_count( (string)$previousToken, "[" ) - $lastBracketEscapedCount;
        $lastBracketAltCount = substr_count( (string)$previousToken, "]" ) - $lastBracketAltEscapedCount;
        if ( ( $lastBracketCount - $lastBracketAltCount ) != 0 )
        {
            $previousToken->append( Fragment::SEPARATOR . (string) $token );
            return true;
        }

        return false;
    }

    protected function isField( Token $token )
    {
        return in_array( (string)$token, $this->fields );
    }

    protected function setIsField( Token $token )
    {
        $token->setType( 'field' );
    }

    protected function isClause( Token $token )
    {
        return in_array( (string)$token, $this->clauses );
    }

    protected function setIsClause( Token $token )
    {
        $token->setType( 'clause' );
    }

    protected function isParameter( Token $token )
    {
        return in_array( (string)$token, $this->parameters );
    }

    protected function setIsParameter( Token $token )
    {
        $token->setType( 'parameter' );
    }

    protected function isOperator( Token $token )
    {
        return in_array( (string)$token, $this->operators );
    }

    protected function setIsOperator( Token $token )
    {
        $token->setType( 'operator' );
    }

    public function getSubQueryResolver()
    {
        return $this->subQueryResolver;
    }

    public function setSubQueryResolver($subQueryResolver): void
    {
        $this->subQueryResolver = $subQueryResolver;
    }
}
