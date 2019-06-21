<?php

namespace Opencontent\QueryLanguage\Parser;

class ValueParser
{
    public static function parseString($string)
    {
        $string = trim($string);
        if (strpos($string, '[') === 0) {
            return self::parseArray($string);
        }

        return new Value($string);
    }

    protected static function parseArray($string)
    {
        $parenthesisParser = new ParenthesisSplitter($string, '[', ']');
        $fragments = $parenthesisParser->run();
        $value = new ArrayValue();
        foreach ($fragments as $wrapper => $items) {
            self::parseValueFragment($items, $value);
        }

        return $value;
    }

    protected static function parseValueFragment($items, ArrayValue $value)
    {
        $skipIndexItemList = array();
        $rewriteIndexItems = array();
        foreach ($items as $index => $item) {
            if (in_array($index, $skipIndexItemList)){
                continue;
            }
            if (isset($rewriteIndexItems[$index])){
                $item = $rewriteIndexItems[$index];
            }
            if (is_string($item)) {
                $parts = array();
                if (strpos($item, "'") !== false) {
                    $item = str_replace("\'", "$", $item);
                    $cleanParts = explode("'", $item);

                    foreach ($cleanParts as $cleanPart) {
                        if ($cleanPart != '') {
                            $cleanValue = trim($cleanPart);
                            if ($cleanValue != ',') {
                                $cleanValue = str_replace("$", "'", $cleanValue);
                                $parts[] = "'$cleanValue'";
                            }
                        }
                    }
                } else {
                    $parts = explode(",", $item);                    
                    $parts = array_map('trim', $parts);
                }

                foreach ($parts as $part) {
                    if (empty($part)){
                        continue;
                    }                    
                    $hashSplit = explode('=>', $part);                                        
                    if (isset($hashSplit[1])) {                        
                        $value->append($hashSplit[0], $hashSplit[1]);
                    } else {
                        $key = null;
                        if ($part == 'raw' && is_array($items[$index+1]) && count($items[$index+1]) == 1){
                            $part .= '[' . $items[$index+1][0] . ']';
                            $skipIndexItemList[] = $index+1;
                            if (isset($items[$index+2]) && is_string($items[$index+2])){
                                $subParts = explode(",", $items[$index+2]);
                                $subPart = array_shift($subParts);                                
                                if (!empty($subPart)){
                                    if (strpos($subPart, '=>') === 0){
                                        $key = $part;
                                        $part = trim(str_replace('=>', '', $subPart));  
                                        if (count($subParts) == 0){   
                                            $skipIndexItemList[] = $index+2;
                                        }
                                    }else{                                    
                                        $part .= $subPart;
                                    }
                                }
                                if (count($subParts) > 0){
                                    $rewriteIndexItems[$index+2] = implode(', ', $subParts);
                                }else{
                                    $skipIndexItemList[] = $index+2;
                                }
                            }
                        }
                        $value->append($key, $part);
                    }
                }
            } else {
                $nestedValue = new ArrayValue();
                self::parseValueFragment($item, $nestedValue);
                $value->append(null, $nestedValue->toArray());
            }
        }
    }

}
