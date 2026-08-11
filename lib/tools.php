<?php

namespace Rover\Notifier;

/**
 * Class Tools
 *
 * @package Rover\Notifier
 * @author  Pavel Shulaev (https://rover-it.me)
 */
class Tools
{
    /**
     * @param        $input
     * @param string $separator
     * @param bool   $strToUpper
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function fromCamelCase($input, string $separator = '-', bool $strToUpper = false): string
    {
        preg_match_all('!([A-Z][A-Z0-9_]*(?=$|[A-Z][a-z0-9_])|[A-Za-z_][a-z0-9_]+)!', $input, $matches);

        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == mb_strtoupper($match) ? mb_strtolower($match) : lcfirst($match);
        }

        $result = implode($separator, $ret);
        if ($strToUpper) {
            $result = mb_strtoupper($result);
        }

        return $result;
    }

    /**
     * @param string $string
     * @param string $separator
     * @param false  $capitalizeFirstCharacter
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function toCamelCase(string $string, string $separator = '-', bool $capitalizeFirstCharacter = false): string
    {
        $str = str_replace($separator, '', ucwords($string, $separator));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
    }
}
