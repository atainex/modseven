<?php
/**
 * UTF8::strrpos
 *
 * @package    KO7
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @copyright  (c) 2005 Harry Fuecks
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt
 */

use \KO7\UTF8;

function _strrpos($str, $search, $offset = 0)
{
    $offset = (int)$offset;

    if (UTF8::is_ascii($str) && UTF8::is_ascii($search)) {
        return strrpos($str, $search, $offset);
    }

    if ($offset === 0) {
        $array = explode($search, $str, -1);
        return isset($array[0]) ? UTF8::strlen(implode($search, $array)) : FALSE;
    }

    $str = UTF8::substr($str, $offset);
    $pos = UTF8::strrpos($str, $search);
    return ($pos === FALSE) ? FALSE : ($pos + $offset);
}
