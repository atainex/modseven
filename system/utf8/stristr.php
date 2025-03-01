<?php
/**
 * UTF8::stristr
 *
 * @package    KO7
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @copyright  (c) 2005 Harry Fuecks
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt
 */

use \KO7\UTF8;

function _stristr($str, $search)
{
    if (UTF8::is_ascii($str) && UTF8::is_ascii($search)) {
        return stristr($str, $search);
    }

    if ($search == '') {
        return $str;
    }

    $str_lower = UTF8::strtolower($str);
    $search_lower = UTF8::strtolower($search);

    preg_match('/^(.*?)' . preg_quote($search_lower, '/') . '/s', $str_lower, $matches);

    if (isset($matches[1])) {
        return substr($str, strlen($matches[1]));
    }

    return FALSE;
}
