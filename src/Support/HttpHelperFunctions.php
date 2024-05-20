<?php

namespace FrockDev\ToolsForLaravel\Support;

class HttpHelperFunctions
{
    public static function buildNestedArrayFromParsedBody(array $parsedBody) {
        //the parsed body is like provider[title] => 'some title', provider[slug] => 'some slug'
        //we need to convert it to ['provider' => ['title' => 'some title', 'slug' => 'some slug']]
        //also we now, that levels can be more than 2, so we need to build nested arrays

        $result = [];
        foreach ($parsedBody as $key => $value) {
            $keys = explode('[', $key);
            $keys = array_map(fn($key) => str_replace(']', '', $key), $keys);
            $nestedArray = [];
            $nestedArray[$keys[count($keys)-1]] = $value;
            for ($i = count($keys)-2; $i >= 0; $i--) {
                $nestedArray = [$keys[$i] => $nestedArray];
            }
            $result = array_merge_recursive($result, $nestedArray);
        }
        return $result;
    }
}