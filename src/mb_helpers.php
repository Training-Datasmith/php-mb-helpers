<?php

declare (strict_types=1);
if (!function_exists('mb_ucwords')) {
    /**
     * Capitalize the first letter of each word in a multibyte string.
     *
     * @param string $str      The input multibyte string.
     * @param string $encoding Character encoding of $str. Defaults to UTF-8.
     * @return string The string with each word's first character uppercased.
     * @complexity O(n) where n is the number of multibyte characters in $str.
     */
    function mb_ucwords($str, $encoding = 'UTF-8')
    {
        $upper = true;
        $res = '';
        for ($i = 0; $i < mb_strlen($str, $encoding); $i++) {
            $c = mb_substr($str, $i, 1, $encoding);
            if ($upper) {
                $c = mb_convert_case($c, MB_CASE_UPPER, $encoding);
                $upper = false;
            }
            if ($c == ' ') {
                $upper = true;
            }
            $res .= $c;
        }
        return $res;
    }
}
if (!function_exists('mb_ucfirst')) {
    /**
     * Capitalize the first character of a multibyte string.
     *
     * @param string $str      The input multibyte string.
     * @param string $encoding Character encoding of $str. Defaults to UTF-8.
     * @return string The string with its first character uppercased.
     */
    function mb_ucfirst($str, $encoding = 'UTF-8')
    {
        $first_letter = mb_substr($str, 0, 1, $encoding);
        $rest = mb_substr($str, 1, mb_strlen($str, $encoding), $encoding);
        return mb_strtoupper($first_letter, $encoding) . $rest;
    }
}
if (!function_exists('mb_strrev')) {
    /**
     * Reverse a multibyte string character by character.
     *
     * @param string $str      The input multibyte string to reverse.
     * @param string $encoding Character encoding of $str. Defaults to UTF-8.
     * @return string The reversed string with multibyte characters preserved intact.
     */
    function mb_strrev($str, $encoding = 'UTF-8')
    {
        $str = mb_convert_encoding($str, 'UTF-16BE', $encoding);
        return mb_convert_encoding(strrev($str), $encoding, 'UTF-16LE');
    }
}
if (!function_exists('mb_str_pad')) {
    /**
     * Pad a multibyte string to a specified length using a pad string.
     *
     * @param string $input      The string to pad.
     * @param int    $pad_length Desired total length of the padded string in multibyte characters.
     * @param string $pad_string The string to pad with. Defaults to a single space.
     * @param int    $pad_type   STR_PAD_RIGHT, STR_PAD_LEFT, or STR_PAD_BOTH.
     * @param string $encoding   Character encoding. Defaults to UTF-8.
     * @return string The padded string.
     */
    function mb_str_pad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT, $encoding = 'UTF-8')
    {
        $diff = strlen($input) - mb_strlen($input, $encoding);
        return str_pad($input, $pad_length + $diff, $pad_string, $pad_type);
    }
}
if (!function_exists('mb_count_chars')) {
    /**
     * Count occurrences of each unique character in a multibyte string.
     *
     * @param string $string   The input multibyte string to analyze.
     * @param int    $mode     Supported modes: 1 = associative array of char => count; 3 = string of unique characters.
     * @param string $encoding Character encoding. Defaults to UTF-8.
     * @return array<string,int>|string Mode 1 returns an array; mode 3 returns a string of unique characters.
     * @throws \Exception When an unsupported mode is requested.
     * @complexity O(n) where n is the number of multibyte characters in $string.
     */
    function mb_count_chars($string, $mode, $encoding = 'UTF-8')
    {
        $l = mb_strlen($string, $encoding);
        $unique = [];
        for ($i = 0; $i < $l; $i++) {
            $char = mb_substr($string, $i, 1, $encoding);
            if (!array_key_exists($char, $unique)) {
                $unique[$char] = 0;
            }
            $unique[$char]++;
        }
        if ($mode == 1) {
            return $unique;
        }
        if ($mode == 3) {
            $res = '';
            foreach ($unique as $index => $count) {
                $res .= $index;
            }
            return $res;
        }
        throw new \Exception('unsupported mode ' . $mode);
    }
}
if (!function_exists('mb_str_split')) {
    /**
     * Split a multibyte string into an array of chunks of given length.
     *
     * @param string $string       The multibyte string to split.
     * @param int    $split_length Number of characters per chunk. Must be >= 1.
     * @param string $encoding     Character encoding. Defaults to UTF-8.
     * @return string[] Array of string chunks; returns [''] on empty input (matching str_split() behaviour).
     * @throws \Exception When $split_length is less than 1.
     */
    function mb_str_split($string, $split_length = 1, $encoding = 'UTF-8')
    {
        if ($split_length <= 0) {
            throw new \Exception('The length of each segment must be greater than zero');
        }
        $ret = [];
        $len = mb_strlen($string, $encoding);
        for ($i = 0; $i < $len; $i += $split_length) {
            $ret[] = mb_substr($string, $i, $split_length, $encoding);
        }
        if (!$ret) {
            // behave like str_split() on empty input
            return [''];
        }
        return $ret;
    }
}