<?php

namespace Opencontent\QueryLanguage;

use Opencontent\QueryLanguage\Parser\Exception;
use Opencontent\QueryLanguage\Parser\Sentence;
use Opencontent\QueryLanguage\Parser\Clause;
use Opencontent\QueryLanguage\Parser\FragmentCollection;
use Opencontent\QueryLanguage\Parser\Fragment;
use Opencontent\QueryLanguage\Parser\TokenFactory;
use Opencontent\QueryLanguage\Parser\Token;
use Opencontent\QueryLanguage\Parser\Item;
use Opencontent\QueryLanguage\Parser\ParenthesisSplitter;


class Parser
{
    protected $string;

    /** @var FragmentCollection */
    protected $tokenizer;

    /** @var Item[] */
    protected $items = array();

    /** @var  Item */
    protected $lastItem;

    /** @var  Item */
    protected $currentItem;

    /** @var Token */
    protected $appendToLast;

    /** @var Query */
    protected $query;

    /** @var TokenFactory */
    protected $tokenFactory;

    public function __construct(Query $query)
    {
        $this->string = $query->getOriginalString();
        $this->query = $query;
    }

    public function setTokenFactory(TokenFactory $tokenFactory)
    {
        $this->tokenFactory = $tokenFactory;

        return $this;
    }

    public function tokenize()
    {
        if ($this->tokenizer === null && $this->tokenFactory instanceof TokenFactory) {
            $this->tokenizer = new FragmentCollection($this->tokenFactory);
            //$this->removeSpaceNearCommas();
            $this->removeSpaceNearSquareBracket();
            $this->checkQuotes();
            $parenthesisParser = new ParenthesisSplitter($this->string);
            $fragments = $parenthesisParser->run();
            foreach ($fragments as $fragment) {
                $this->tokenizer->addRaw($fragment);
            }
            $this->tokenizer->isValid();
        }

        return $this;
    }

    protected function removeSpaceNearCommas()
    {
        $this->string = str_replace(', ', ',', $this->string);
        $this->string = str_replace(' ,', ',', $this->string);
    }

    protected function removeSpaceNearSquareBracket()
    {
        $this->string = str_replace('[ ', '[', $this->string);
        $this->string = str_replace(' ]', ']', $this->string);
    }

    protected function checkQuotes()
    {
        $quoteEscapedCount = substr_count($this->string, "\'");
        $quoteCount = substr_count($this->string, "'") - $quoteEscapedCount;
        if (!( $quoteCount % 2 == 0 )) {
            throw new Exception("Syntax error: unbalanced quotes");
        }
    }

    protected function filterFragment($data)
    {
        $return = array();
        if (is_string($data)) {
            $item = new Fragment($data, $this->tokenFactory);
            if ($item->isValid()) {
                $return[] = $item;
            }
        } else {
            foreach ($data as $item) {
                $return[] = $this->filterFragment($item);
            }
        }

        return $return;
    }

    public function parse()
    {
        if ($this->tokenizer === null) {
            $this->tokenize();
        }

        $this->query->appendFilter($this->parseFragmentCollection($this->tokenizer->getFragments()));
        $this->query->appendParameter($this->parseFragmentCollection($this->tokenizer->getFragmentParameters()));

        return $this->query;
    }

    protected function parseFragmentCollection($collection)
    {
        $currentItem = new Item();
        foreach ($collection as $fragment) {
            $this->parseFragment($fragment, $currentItem);
        }

        return $currentItem;
    }

    protected function parseFragment($fragment, Item $currentItem)
    {
        if ($fragment instanceof Sentence) {
            $currentItem->add($fragment);
        }

        if ($fragment instanceof Clause) {
            $currentItem->clause = (string)$fragment;
        }

        if ($fragment instanceof FragmentCollection) {
            $currentItem->addChild($this->parseFragmentCollection($fragment));
        }

        return $currentItem;
    }
}
