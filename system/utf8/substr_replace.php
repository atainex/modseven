<?php
/**
 * UTF8::substr_replace
 *
 * @package    KO7
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @copyright  (c) 2005 Harry Fuecks
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt
 */

use \KO7\UTF8;

function _substr_replace($str, $replacement, $offset, $length = NULL)
{
    if (UTF8::is_ascii($str)) {
        return ($length === null) ? substr_replace($str, $replacement, $offset) : substr_replace($str, $replacement,
            $offset, $length);
    }

    $length = ($length === NULL) ? UTF8::strlen($str) : (int)$length;
    preg_match_all('/./us', $str, $str_array);
    preg_match_all('/./us', $replacement, $replacement_array);

    array_splice($str_array[0], $offset, $length, $replacement_array[0]);
    return implode('', $str_array[0]);
}
