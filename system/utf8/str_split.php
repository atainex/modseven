<?php
/**
 * UTF8::str_split
 *
 * @package    KO7
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @copyright  (c) 2005 Harry Fuecks
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt
 */

use \KO7\UTF8;

function _str_split($str, $split_length = 1)
{
    $split_length = (int)$split_length;

    if (UTF8::is_ascii($str)) {
        return str_split($str, $split_length);
    }

    if ($split_length < 1) {
        return false;
    }

    if (UTF8::strlen($str) <= $split_length) {
        return [$str];
    }

    preg_match_all('/.{' . $split_length . '}|[^\x00]{1,' . $split_length . '}$/us', $str, $matches);

    return $matches[0];
}
