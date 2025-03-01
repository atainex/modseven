<?php
/**
 * Contains debugging and dumping tools.
 *
 * @package    KO7
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace KO7;

class Debug
{

    /**
     * Returns an HTML string of debugging information about any number of
     * variables, each wrapped in a "pre" tag:
     *
     *     // Displays the type and value of each variable
     *     echo Debug::vars($foo, $bar, $baz);
     *
     * @param mixed $var,... variable to debug
     * @return  string|null
     */
    public static function vars(): ?string
    {
        if (func_num_args() === 0) {
            return null;
        }

        // Get all passed variables
        $variables = func_get_args();

        $output = [];
        foreach ($variables as $var) {
            $output[] = self::_dump($var, 1024);
        }

        return '<pre class="debug">' . implode("\n", $output) . '</pre>';
    }

    /**
     * Helper for Debug::dump(), handles recursion in arrays and objects.
     *
     * @param mixed $var variable to dump
     * @param integer $length maximum length of strings
     * @param integer $limit recursion limit
     * @param integer $level current recursion level (internal usage only!)
     * @return  string|null
     */
    protected static function _dump(& $var, int $length = 128, int $limit = 10, int $level = 0): ?string
    {
        if ($var === NULL) {
            return '<small>NULL</small>';
        }
        if (is_bool($var)) {
            return '<small>bool</small> ' . ($var ? 'TRUE' : 'FALSE');
        }
        if (is_float($var)) {
            return '<small>float</small> ' . $var;
        }
        if (is_resource($var)) {
            if (($type = get_resource_type($var)) === 'stream' AND $meta = stream_get_meta_data($var)) {
                $meta = stream_get_meta_data($var);

                if (isset($meta['uri'])) {
                    $file = $meta['uri'];

                    if (function_exists('stream_is_local')) {
                        // Only exists on PHP >= 5.2.4
                        if (stream_is_local($file)) {
                            $file = Debug::path($file);
                        }
                    }

                    return '<small>resource</small><span>(' . $type . ')</span> ' . htmlspecialchars($file, ENT_NOQUOTES, Core::$charset);
                }
            } else {
                return '<small>resource</small><span>(' . $type . ')</span>';
            }
        } elseif (is_string($var)) {
            // Clean invalid multibyte characters. iconv is only invoked
            // if there are non ASCII characters in the string, so this
            // isn't too much of a hit.
            $var = UTF8::clean($var, Core::$charset);

            if (UTF8::strlen($var) > $length) {
                // Encode the truncated string
                $str = htmlspecialchars(UTF8::substr($var, 0, $length), ENT_NOQUOTES, Core::$charset) . '&nbsp;&hellip;';
            } else {
                // Encode the string
                $str = htmlspecialchars($var, ENT_NOQUOTES, Core::$charset);
            }

            return '<small>string</small><span>(' . strlen($var) . ')</span> "' . $str . '"';
        } elseif (is_array($var)) {
            $output = [];

            // Indentation for this variable
            $space = str_repeat($s = '    ', $level);

            static $marker;

            if ($marker === NULL) {
                // Make a unique marker - force it to be alphanumeric so that it is always treated as a string array key
                $marker = uniqid("\x00", false) . "x";
            }

            if (empty($var)) {
                // Do nothing
            } elseif (isset($var[$marker])) {
                $output[] = "(\n$space$s*RECURSION*\n$space)";
            } elseif ($level < $limit) {
                $output[] = '<span>(';

                $var[$marker] = TRUE;
                foreach ($var as $key => & $val) {
                    if ($key === $marker) {
                        continue;
                    }
                    if (!is_int($key)) {
                        $key = '"' . htmlspecialchars($key, ENT_NOQUOTES, Core::$charset) . '"';
                    }

                    $output[] = "$space$s$key => " . self::_dump($val, $length, $limit, $level + 1);
                }
                unset($val, $var[$marker]);

                $output[] = "$space)</span>";
            } else {
                // Depth too great
                $output[] = "(\n$space$s...\n$space)";
            }

            return '<small>array</small><span>(' . count($var) . ')</span> ' . implode("\n", $output);
        } elseif (is_object($var)) {
            // Copy the object as an array
            $array = (array)$var;

            $output = [];

            // Indentation for this variable
            $space = str_repeat($s = '    ', $level);

            $hash = spl_object_hash($var);

            // Objects that are being dumped
            static $objects = [];

            if (empty($var)) {
                // Do nothing
            } elseif (isset($objects[$hash])) {
                $output[] = "{\n$space$s*RECURSION*\n$space}";
            } elseif ($level < $limit) {
                $output[] = '<code>{';

                $objects[$hash] = TRUE;
                foreach ($array as $key => & $val) {
                    if ($key[0] === "\x00") {
                        // Determine if the access is protected or protected
                        $access = '<small>' . (($key[1] === '*') ? 'protected' : 'private') . '</small>';

                        // Remove the access level from the variable name
                        $key = substr($key, strrpos($key, "\x00") + 1);
                    } else {
                        $access = '<small>public</small>';
                    }

                    $output[] = "$space$s$access $key => " . self::_dump($val, $length, $limit, $level + 1);
                }
                unset($val, $objects[$hash]);

                $output[] = "$space}</code>";
            } else {
                // Depth too great
                $output[] = "{\n$space$s...\n$space}";
            }

            return '<small>object</small> <span>' . get_class($var) . '(' . count($array) . ')</span> ' . implode("\n", $output);
        } else {
            return '<small>' . gettype($var) . '</small> ' . htmlspecialchars(print_r($var, TRUE), ENT_NOQUOTES, Core::$charset);
        }

        return null;
    }

    /**
     * Removes application, system, modpath, or docroot from a filename,
     * replacing them with the plain text equivalents. Useful for debugging
     * when you want to display a shorter path.
     *
     * @param string $file path to debug
     * @return  string
     */
    public static function path(string $file): string
    {
        if (strpos($file, APPPATH) === 0) {
            return 'APPPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(APPPATH));
        }
        if (strpos($file, SYSPATH) === 0) {
            return 'SYSPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(SYSPATH));
        }
        if (strpos($file, MODPATH) === 0) {
            return 'MODPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(MODPATH));
        }
        if (strpos($file, DOCROOT) === 0) {
            return 'DOCROOT' . DIRECTORY_SEPARATOR . substr($file, strlen(DOCROOT));
        }

        return 'UNKNOWN';
    }

    /**
     * Returns an HTML string of information about a single variable.
     *
     * Borrows heavily on concepts from the Debug class of [Nette](http://nettephp.com/).
     *
     * @param mixed $value variable to dump
     * @param integer $length maximum length of strings
     * @param integer $level_recursion recursion limit
     * @return  string
     */
    public static function dump($value, int $length = 128, int $level_recursion = 10): string
    {
        return self::_dump($value, $length, $level_recursion);
    }

    /**
     * Returns an array of HTML strings that represent each step in the backtrace.
     *
     * @param array $trace
     *
     * @return  array
     * @throws \ReflectionException
     *
     */
    public static function trace(?array $trace = NULL): array
    {
        if ($trace === NULL) {
            // Start a new trace
            $trace = debug_backtrace();
        }

        // Non-standard function calls
        $statements = ['include', 'include_once', 'require', 'require_once'];

        $output = [];
        foreach ($trace as $step) {
            if (!isset($step['function'])) {
                // Invalid trace step
                continue;
            }

            if (isset($step['file']) && isset($step['line'])) {
                // Include the source of this step
                $source = self::source($step['file'], $step['line']);
            }

            if (isset($step['file'])) {
                $file = $step['file'];

                if (isset($step['line'])) {
                    $line = $step['line'];
                }
            }

            // function()
            $function = $step['function'];

            if (in_array($step['function'], $statements, true)) {
                if (empty($step['args'])) {
                    // No arguments
                    $args = [];
                } else {
                    // Sanitize the file path
                    $args = [$step['args'][0]];
                }
            } elseif (isset($step['args'])) {
                if (!function_exists($step['function']) || strpos($step['function'], '{closure}') !== FALSE) {
                    // Introspection on closures or language constructs in a stack trace is impossible
                    $params = NULL;
                } else {
                    if (isset($step['class'])) {
                        if (method_exists($step['class'], $step['function'])) {
                            $reflection = new \ReflectionMethod($step['class'], $step['function']);
                        } else {
                            $reflection = new \ReflectionMethod($step['class'], '__call');
                        }
                    } else {
                        $reflection = new \ReflectionFunction($step['function']);
                    }

                    // Get the function parameters
                    $params = $reflection->getParameters();
                }

                $args = [];

                foreach ($step['args'] as $i => $arg) {
                    if (isset($params[$i])) {
                        // Assign the argument by the parameter name
                        $args[$params[$i]->name] = $arg;
                    } else {
                        // Assign the argument by number
                        $args[$i] = $arg;
                    }
                }
            }

            if (isset($step['class'])) {
                // Class->method() or Class::method()
                $function = $step['class'] . $step['type'] . $step['function'];
            }

            $output[] = [
                'function' => $function,
                'args' => $args ?? null,
                'file' => $file ?? null,
                'line' => $line ?? null,
                'source' => $source ?? null,
            ];

            unset($function, $args, $file, $line, $source);
        }

        return $output;
    }

    /**
     * Returns an HTML string, highlighting a specific line of a file, with some
     * number of lines padded above and below.
     *
     * @param string $file file to open
     * @param integer $line_number line number to highlight
     * @param integer $padding number of padding lines
     * @return  string|FALSE   source of file
     */
    public static function source(string $file, int $line_number, int $padding = 5)
    {
        if (!$file || !is_readable($file)) {
            // Continuing will cause errors
            return FALSE;
        }

        // Open the file and set the line position
        $file = fopen($file, 'r');
        $line = 0;

        // Set the reading range
        $range = ['start' => $line_number - $padding, 'end' => $line_number + $padding];

        // Set the zero-padding amount for line numbers
        $format = '% ' . strlen($range['end']) . 'd';

        $source = '';
        while (($row = fgets($file)) !== FALSE) {
            // Increment the line number
            if (++$line > $range['end']) {
                break;
            }

            if ($line >= $range['start']) {
                // Make the row safe for output
                $row = htmlspecialchars($row, ENT_NOQUOTES, Core::$charset);

                // Trim whitespace and sanitize the row
                $row = '<span class="number">' . sprintf($format, $line) . '</span> ' . $row;

                if ($line === $line_number) {
                    // Apply highlighting to this row
                    $row = '<span class="line highlight">' . $row . '</span>';
                } else {
                    $row = '<span class="line">' . $row . '</span>';
                }

                // Add to the captured source
                $source .= $row;
            }
        }

        // Close the file
        fclose($file);

        return '<pre class="source"><code>' . $source . '</code></pre>';
    }

}
