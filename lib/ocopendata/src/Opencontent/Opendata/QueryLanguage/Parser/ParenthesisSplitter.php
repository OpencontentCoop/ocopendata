<?php

namespace Opencontent\QueryLanguage\Parser;

class ParenthesisSplitter
{
    protected $openChar;
    protected $closeChar;
    protected $string = null;
    protected $length = 0;
    protected $chars = array();

    protected $stack = null;
    protected $current = null;
    protected $position = null;
    protected $lastPosition = 0;
    protected $buffer_start = null;

    public function __construct($string, $open = '(', $close = ')')
    {
        $this->openChar = $open;
        $this->closeChar = $close;

        if (!$string) {
            throw new Exception("Empty string");
        }

        //        if ( $string[0] == $this->openChar )
        //        {
        //            $string = trim( mb_substr( $string, 1, -1 ) );
        //        }

        $originalString = $string;

        $this->chars = array();

        $strlen = mb_strlen($string);
        while ($strlen) {
            $this->chars[] = mb_substr($string, 0, 1, "UTF-8");
            $string = mb_substr($string, 1, $strlen, "UTF-8");
            $strlen = mb_strlen($string);
        }

        if (!$this->isBalanced()) {
            throw new Exception("Unbalanced parenthesis in \"$string\"" . mb_strlen($string));
        }

        $this->string = $originalString;
        $this->length = mb_strlen($this->string);
    }

    protected function isBalanced()
    {
        $bal = 0;
        foreach ($this->chars as $ch) {
            if ($ch == $this->openChar) {
                $bal++;
            } elseif ($ch == $this->closeChar) {
                $bal--;
            }
        }

        return ( $bal == 0 );
    }


    public function run()
    {
        $this->current = array();
        $this->stack = array();

        // look at each character
        foreach ($this->chars as $index => $ch) {
            $this->position = $index;
            switch ($ch) {
                case $this->openChar:
                    $this->push();
                    // push current scope to the stack an begin a new scope
                    array_push($this->stack, $this->current);
                    $this->current = array();
                    break;
                case $this->closeChar:
                    $this->push();
                    // save current scope
                    $t = $this->current;
                    // get the last scope from stack
                    $this->current = array_pop($this->stack);
                    // add just saved scope to current scope
                    $this->current[] = $t;
                    $this->lastPosition = $this->position + 1;
                    break;
                /*
                 case ' ':
                     // make each word its own token
                     $this->push();
                     break;
                 */
                default:
                    // remember the offset to do a string capture later
                    // could've also done $buffer .= $string[$position]
                    // but that would just be wasting resources…
                    if ($this->buffer_start === null) {
                        $this->buffer_start = $this->position;
                    }
            }
        }
        if ($this->lastPosition + 1 < $this->length) {
            $this->current[] = trim(
                mb_substr($this->string, $this->lastPosition, $this->length - $this->lastPosition)
            );
        }

        return $this->current;
    }

    protected function push()
    {
        if ($this->buffer_start !== null) {
            // extract string from buffer start to current position
            $buffer = mb_substr(
                $this->string,
                $this->buffer_start,
                $this->position - $this->buffer_start
            );
            // clean buffer
            $this->buffer_start = null;
            // throw token into current scope
            $this->current[] = trim($buffer);
        }
    }

}
