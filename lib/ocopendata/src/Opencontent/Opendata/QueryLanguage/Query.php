<?php

namespace Opencontent\QueryLanguage;

use Opencontent\QueryLanguage\Converter\StringQueryConverter;
use Opencontent\QueryLanguage\Converter\QueryConverter;
use Opencontent\QueryLanguage\Parser;
use Opencontent\QueryLanguage\Parser\TokenFactory;
use Opencontent\QueryLanguage\Parser\Item;
use SplObjectStorage;

class Query
{
    /**
     * @var Item[]|SplObjectStorage
     */
    protected $filters;

    /**
     * @var Item[]|SplObjectStorage
     */
    protected $parameters;

    protected $originalString;

    /**
     * @var TokenFactory
     */
    protected $tokenFactory;

    protected $parsed = false;

    /**
     * @var QueryConverter
     */
    protected $converter;

    /**
     * @var Parser
     */
    protected $parser;

    public function __construct($string)
    {
        $this->originalString = $string;
        $this->filters = new SplObjectStorage();
        $this->parameters = new SplObjectStorage();
        $this->parser = new Parser($this);
    }

    public function parse()
    {
        if ($this->parser instanceof Parser) {
            if ($this->tokenFactory instanceof TokenFactory) {
                $this->parser
                    ->setTokenFactory($this->tokenFactory)
                    ->parse();
                $this->parsed = true;
            } else {
                throw new \Exception("TokenFactory not found");
            }
        } else {
            throw new \Exception("Parser not found");
        }
    }

    /**
     * @return mixed
     */
    public function convert()
    {
        if ($this->parsed === false) {
            $this->parse();
        }

        if ($this->converter instanceof QueryConverter) {
            $this->converter->setQuery($this);

            return $this->converter->convert();
        }

        return null;
    }

    public function __toString()
    {
        $string = '';
        try {
            if ($this->parsed === false) {
                $this->parse();
            }

            $converter = new StringQueryConverter();
            if ($converter instanceof QueryConverter) {
                $converter->setQuery($this);
                $string = $converter->convert();
            }
        } catch (\Exception $e) {
            // evita eccezione nel casting (string)
        }

        return $string;
    }

    public function getOriginalString()
    {
        return $this->originalString;
    }

    public function setTokenFactory(TokenFactory $tokenFactory)
    {
        $this->tokenFactory = $tokenFactory;

        return $this;
    }

    public function setConverter(QueryConverter $converter)
    {
        $this->converter = $converter;

        return $this;
    }

    public function setParser(Parser $parser)
    {
        $this->parser = $parser;

        return $this;
    }

    public function appendFilter(Item $item)
    {
        if ($item->hasSentences()) {
            $this->filters->attach($item, (string)$item);
        }
    }

    /**
     * @return Item[]
     */
    public function getFilters()
    {
        $items = array();
        foreach ($this->filters as $item) {
            $items[$item->id] = $item;
        }

        return $items;
    }

    public function appendParameter(Item $item)
    {
        $this->parameters->attach($item, (string)$item);
    }

    /**
     * @return Item[]
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    public function setParameter($key, $value)
    {
        $keyToken = new Parser\Token();
        $keyToken->setToken((string)$key);
        $keyToken->setType('value');

        $valueToken = new Parser\Token();
        $valueToken->setToken((string)$value);
        $valueToken->setType('value');

        $done = false;
        foreach ($this->getParameters() as $item) {
            foreach ($item->getSentences() as $parameter) {
                if ($parameter instanceof Parser\Parameter
                    && (string)$parameter->getKey() == $key
                ) {
                    $parameter->setValue($valueToken);
                    $done = true;
                }
            }
        }
        if (!$done) {

            $newParameter = new Parser\Parameter();
            $newParameter->setKey($keyToken);
            $newParameter->setValue($valueToken);

            $newItem = new Item();
            $newItem->add($newParameter);

            $this->appendParameter($newItem);
        }

        return $this;
    }

    public function merge(Query $query)
    {
        $query->parse();

        foreach ($query->getFilters() as $item) {
            $item->id = uniqid('merged', $item->id);
            $this->appendFilter($item);
        }

        foreach ($query->getParameters() as $item) {
            $item->id = uniqid('merged', $item->id);
            $this->appendParameter($item);
        }
    }
}
