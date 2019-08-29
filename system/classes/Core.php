<?php
/**
 * Contains the most low-level helpers methods in KO7:
 *
 * - Environment initialization
 * - Locating files within the cascading filesystem
 * - Auto-loading and transparent extension of classes
 * - Variable and path debugging
 *
 * @package    KO7
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace KO7;

class Core
{

    // Release version and codename
    public const VERSION = '3.3.9';
    public const CODENAME = 'karlsruhe';

    // Common environment type constants for consistency and convenience
    public const PRODUCTION = 10;
    public const STAGING = 20;
    public const TESTING = 30;
    public const DEVELOPMENT = 40;

    // Format of cache files: header, cache name, and data
    public const FILE_CACHE = ":header \n\n// :name\n\n:data\n";

    /**
     * Namespace of classes inside app folder
     * @var string
     */
    public static $app_ns;

    /**
     * @var  int  Current environment name
     */
    public static $environment = Core::DEVELOPMENT;

    /**
     * @var  boolean  True if KO7 is running on windows
     */
    public static $is_windows = false;

    /**
     * @var  string
     */
    public static $content_type = 'text/html';

    /**
     * @var  string  character set of input and output
     */
    public static $charset = 'utf-8';

    /**
     * @var  string  the name of the server KO7 is hosted upon
     */
    public static $server_name = '';

    /**
     * @var  array   list of valid host names for this instance
     */
    public static $hostnames = [];

    /**
     * @var  string  base URL to the application
     */
    public static $base_url = '/';

    /**
     * @var  string  Application index file, added to links generated by KO7. Set by [KO7::init]
     */
    public static $index_file = 'index.php';

    /**
     * @var  string  Cache directory, used by [KO7::cache]. Set by [KO7::init]
     */
    public static $cache_dir;

    /**
     * @var  integer  Default lifetime for caching, in seconds, used by [KO7::cache]. Set by [KO7::init]
     */
    public static $cache_life = 60;

    /**
     * @var  boolean  Whether to use internal caching for [KO7::find_file], does not apply to [KO7::cache]. Set by [KO7::init]
     */
    public static $caching = false;

    /**
     * @var  boolean  Whether to enable [profiling](KO7/profiling). Set by [KO7::init]
     */
    public static $profiling = true;

    /**
     * @var  boolean  Enable KO7 catching and displaying PHP errors and exceptions. Set by [KO7::init]
     */
    public static $errors = true;

    /**
     * @var  array  Types of errors to display at shutdown
     */
    public static $shutdown_errors = [E_PARSE, E_ERROR, E_USER_ERROR];

    /**
     * @var  boolean  set the X-Powered-By header
     */
    public static $expose = false;

    /**
     * @var  Log|null  logging object
     */
    public static $log;

    /**
     * @var  Config | null  config object
     */
    public static $config;

    /**
     * @var  boolean  Has [KO7::init] been called?
     */
    protected static $_init = false;

    /**
     * @var  array   Currently active modules
     */
    protected static $_modules = [];

    /**
     * @var  array   Include paths that are used to find files
     */
    protected static $_paths = [APPPATH, SYSPATH];

    /**
     * @var  array   File path cache, used when caching is true in [KO7::init]
     */
    protected static $_files = [];

    /**
     * @var  boolean  Has the file path cache changed during this execution?  Used internally when when caching is true in [KO7::init]
     */
    protected static $_files_changed = false;

    /**
     * Initializes the environment:
     *
     * - Determines the current environment
     * - Set global settings
     * - Sanitizes GET, POST, and COOKIE variables
     * - Converts GET, POST, and COOKIE variables to the global character set
     *
     * @param array $settings Array of settings.  See above.
     * @return  void
     * @throws  Exception
     */
    public static function init(?array $settings = NULL): void
    {
        if (static::$_init) {
            // Do not allow execution twice
            return;
        }

        // KO7 is now initialized
        static::$_init = TRUE;

        if (isset($settings['profile'])) {
            // Enable profiling
            static::$profiling = (bool)$settings['profile'];
        }

        // Start an output buffer
        ob_start();

        if (isset($settings['errors'])) {
            // Enable error handling
            static::$errors = (bool)$settings['errors'];
        }

        if (static::$errors === TRUE) {
            // Enable KO7 exception handling, adds stack traces and error source.
            set_exception_handler([Exception::class, 'handler']);

            // Enable KO7 error handling, converts all PHP errors to exceptions.
            set_error_handler(['\KO7\Core', 'error_handler']);
        }

        /**
         * Enable xdebug parameter collection in development mode to improve fatal stack traces.
         */
        if (static::$environment === static::DEVELOPMENT && extension_loaded('xdebug')) {
            ini_set('xdebug.collect_params', 3);
        }

        // Enable the KO7 shutdown handler, which catches E_FATAL errors.
        register_shutdown_function(['\KO7\Core', 'shutdown_handler']);

        if (isset($settings['expose'])) {
            static::$expose = (bool)$settings['expose'];
        }

        // Determine if we are running in a Windows environment
        static::$is_windows = (DIRECTORY_SEPARATOR === '\\');

        if (isset($settings['cache_dir'])) {
            if (!is_dir($settings['cache_dir'])) {
                // Create the cache directory
                if (!mkdir($concurrentDirectory = $settings['cache_dir'], 0755,
                        true) && !is_dir($concurrentDirectory)) {
                    throw new Exception('Directory ":dir" was not created', [
                        ':dir' => $concurrentDirectory
                    ]);
                }

                // Set permissions (must be manually set to fix umask issues)
                chmod($settings['cache_dir'], 0755);
            }

            // Set the cache directory path
            static::$cache_dir = realpath($settings['cache_dir']);
        } else {
            // Use the default cache directory
            static::$cache_dir = APPPATH . 'cache';
        }

        if (!is_writable(static::$cache_dir)) {
            throw new Exception('Directory :dir must be writable',
                [':dir' => Debug::path(static::$cache_dir)]);
        }

        if (isset($settings['cache_life'])) {
            // Set the default cache lifetime
            static::$cache_life = (int)$settings['cache_life'];
        }

        if (isset($settings['caching'])) {
            // Enable or disable internal caching
            static::$caching = (bool)$settings['caching'];
        }

        if (static::$caching === TRUE) {
            // Load the file path cache
            static::$_files = self::cache('KO7::find_file()');
        }

        if (isset($settings['charset'])) {
            // Set the system character set
            static::$charset = strtolower($settings['charset']);
        }

        if (function_exists('mb_internal_encoding')) {
            // Set the MB extension encoding to the same character set
            mb_internal_encoding(static::$charset);
        }

        if (isset($settings['base_url'])) {
            // Set the base URL
            static::$base_url = rtrim($settings['base_url'], '/') . '/';
        }

        if (isset($settings['index_file'])) {
            // Set the index file
            static::$index_file = trim($settings['index_file'], '/');
        }

        // Sanitize all request variables
        $_GET = self::sanitize($_GET);
        $_POST = self::sanitize($_POST);
        $_COOKIE = self::sanitize($_COOKIE);

        // Load the logger if one doesn't already exist
        if (!static::$log instanceof Log) {
            static::$log = Log::instance();
        }

        // Load the config if one doesn't already exist
        if (!static::$config instanceof Config) {
            static::$config = new Config;
        }
    }

    /**
     * Cache variables using current cache module if enabled, if not uses KO7::file_cache
     *
     * @param string $name name of the cache
     * @param mixed $data data to cache
     * @param integer $lifetime number of seconds the cache is valid for
     * @return  mixed    for getting
     * @return  boolean  for setting
     * @throws  Exception
     */
    public static function cache(string $name, $data = NULL, ?int $lifetime = NULL)
    {
        //in case the KO7_Cache is not yet loaded we need to use the normal cache...sucks but happens onload
        if (class_exists('KO7_Cache')) {
            //deletes the cache
            if ($lifetime === 0) {
                return Cache::instance()->delete($name);
            }

            //no data provided we read
            if ($data === NULL) {
                return Cache::instance()->get($name);
            }
            //saves data
            return Cache::instance()->set($name, $data, $lifetime);
        }
        return self::file_cache($name, $data, $lifetime);
    }

    /**
     * Provides simple file-based caching for strings and arrays:
     *
     * All caches are stored as PHP code, generated with [var_export][ref-var].
     * Caching objects may not work as expected. Storing references or an
     * object or array that has recursion will cause an E_FATAL.
     *
     * The cache directory and default cache lifetime is set by [KO7::init]
     *
     * [ref-var]: http://php.net/var_export
     *
     * @param string $name name of the cache
     * @param mixed $data data to cache
     * @param integer $lifetime number of seconds the cache is valid for
     * @return  mixed    for getting
     * @return  boolean  for setting
     * @throws  Exception
     */
    public static function file_cache(?string $name, $data = NULL, ?int $lifetime = NULL)
    {
        // Cache file is a hash of the name
        $file = sha1($name) . '.txt';

        // Cache directories are split by keys to prevent filesystem overload
        $dir = static::$cache_dir . DIRECTORY_SEPARATOR . $file[0] . $file[1] . DIRECTORY_SEPARATOR;

        if ($lifetime === NULL) {
            // Use the default lifetime
            $lifetime = static::$cache_life;
        }

        if ($data === NULL) {
            if (is_file($dir . $file)) {
                if ((time() - filemtime($dir . $file)) < $lifetime) {
                    // Return the cache
                    try {
                        return unserialize(file_get_contents($dir . $file), null);
                    } catch (\Exception $e) {
                        // Cache is corrupt, let return happen normally.
                    }
                } else {
                    try {
                        // Cache has expired
                        unlink($dir . $file);
                    } catch (\Exception $e) {
                        // Cache has mostly likely already been deleted,
                        // let return happen normally.
                    }
                }
            }

            // Cache not found
            return NULL;
        }

        if (!is_dir($dir)) {
            // Create the cache directory
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new Exception('Directory ":dir" was not created', [
                    ':dir' => $dir
                ]);
            }

            // Set permissions (must be manually set to fix umask issues)
            chmod($dir, 0777);
        }

        // Force the data to be a string
        $data = serialize($data);

        try {
            // Write the cache
            return (bool)file_put_contents($dir . $file, $data, LOCK_EX);
        } catch (\Exception $e) {
            // Failed to write cache
            return FALSE;
        }
    }

    /**
     * Recursively sanitizes an input variable:
     *
     * - Normalizes all newlines to LF
     *
     * @param mixed $value any variable
     * @return  mixed   sanitized variable
     */
    public static function sanitize($value)
    {
        if (is_array($value) || is_object($value)) {
            foreach ($value as $key => $val) {
                // Recursively clean each value
                $value[$key] = self::sanitize($val);
            }
        } elseif (is_string($value)) {
            if (strpos($value, "\r") !== FALSE) {
                // Standardize newlines
                $value = str_replace(["\r\n", "\r"], "\n", $value);
            }
        }

        return $value;
    }

    /**
     * Cleans up the environment:
     *
     * - Restore the previous error and exception handlers
     * - Destroy the KO7::$log and KO7::$config objects
     *
     * @return  void
     */
    public static function deinit(): void
    {
        if (static::$_init) {

            if (static::$errors) {
                // Go back to the previous error handler
                restore_error_handler();

                // Go back to the previous exception handler
                restore_exception_handler();
            }

            // Destroy objects created by init
            static::$log = static::$config = NULL;

            // Reset internal storage
            static::$_modules = static::$_files = [];
            static::$_paths = [APPPATH, SYSPATH];

            // Reset file cache status
            static::$_files_changed = FALSE;

            // KO7 is no longer initialized
            static::$_init = FALSE;
        }
    }

    /**
     * Changes the currently enabled modules. Module paths may be relative
     * or absolute, but must point to a directory:
     *
     * @param array $modules list of module paths
     * @return  array   enabled modules
     */
    public static function modules(?array $modules = NULL): array
    {
        if ($modules === NULL) {
            // Not changing modules, just return the current set
            return static::$_modules;
        }

        // Start a new list of include paths, APPPATH first
        $paths = [APPPATH];

        foreach ($modules as $name => $path) {
            if (is_dir($path)) {
                // Add the module to include paths
                $paths[] = $modules[$name] = realpath($path) . DIRECTORY_SEPARATOR;
            } else {
                // This module is invalid, remove it
                throw new Exception('Attempted to load an invalid or missing module \':module\' at \':path\'', [
                    ':module' => $name,
                    ':path' => Debug::path($path),
                ]);
            }
        }

        // Finish the include paths by adding SYSPATH
        $paths[] = SYSPATH;

        // Set the new include paths
        static::$_paths = $paths;

        // Set the current module list
        static::$_modules = $modules;

        foreach (static::$_modules as $path) {
            $init = $path . 'init.php';

            if (is_file($init)) {
                // Include the module initialization file once
                require_once $init;
            }
        }

        return static::$_modules;
    }

    /**
     * Returns the the currently active include paths, including the
     * application, system, and each module's path.
     *
     * @return  array
     */
    public static function include_paths(): array
    {
        return static::$_paths;
    }

    /**
     * Recursively finds all of the files in the specified directory at any
     * location in the [Cascading Filesystem](ko7/files), and returns an
     * array of all the files found, sorted alphabetically.
     *
     * @param string $directory directory name
     * @param array $paths list of paths to search
     * @param string|array $ext only list files with this extension
     * @param bool $sort sort alphabetically
     *
     * @return  array
     */
    public static function list_files(?string $directory = NULL, array $paths = NULL, $ext = NULL, bool $sort = TRUE): array
    {
        if ($directory !== NULL) {
            // Add the directory separator
            $directory .= DIRECTORY_SEPARATOR;
        }

        if ($paths === NULL) {
            // Use the default paths
            $paths = static::$_paths;
        }

        if (is_string($ext)) {
            // convert string extension to array
            $ext = [$ext];
        }

        // Create an array for the files
        $found = [];

        foreach ($paths as $path) {
            if (is_dir($path . $directory)) {
                // Create a new directory iterator
                foreach (new DirectoryIterator($path . $directory) as $file) {
                    // Get the file name
                    $filename = $file->getFilename();

                    if (strpos($filename, '.') === 0 || $filename[strlen($filename) - 1] === '~') {
                        // Skip all hidden files and UNIX backup files
                        continue;
                    }

                    // Relative filename is the array key
                    $key = $directory . $filename;

                    if ($file->isDir()) {
                        if ($sub_dir = self::list_files($key, $paths, $ext, $sort)) {
                            if (isset($found[$key])) {
                                // Append the sub-directory list
                                $found[$key] += $sub_dir;
                            } else {
                                // Create a new sub-directory list
                                $found[$key] = $sub_dir;
                            }
                        }
                    } elseif ($ext === NULL || in_array('.' . $file->getExtension(), $ext, TRUE)) {
                        if (!isset($found[$key])) {
                            // Add new files to the list
                            $found[$key] = realpath($file->getPathname());
                        }
                    }
                }
            }
        }

        if ($sort) {
            // Sort the results alphabetically
            ksort($found);
        }

        return $found;
    }

    /**
     * Get a message from a file. Messages are arbitrary strings that are stored
     * in the `messages/` directory and reference by a key. Translation is not
     * performed on the returned values.  See [message files](ko7/files/messages)
     * for more information.
     *
     * @param string $file file name
     * @param string $path key path to get
     * @param mixed $default default value if the path does not exist
     * @return  string  message string for the given path
     * @return  array   complete message list, when no path is specified
     */
    public static function message(string $file, string $path = NULL, $default = NULL)
    {
        static $messages;

        if (!isset($messages[$file])) {
            // Create a new message list
            $messages[$file] = [];

            if ($files = self::find_file('messages', $file)) {
                foreach ($files as $f) {
                    // Combine all the messages recursively
                    $messages[$file] = Arr::merge($messages[$file], self::load($f));
                }
            }
        }

        if ($path === NULL) {
            // Return all of the messages
            return $messages[$file];
        }

        // Get a message using the path
        return Arr::path($messages[$file], $path, $default);
    }

    /**
     * Searches for a file in the [Cascading Filesystem](ko7/files), and
     * returns the path to the file that has the highest precedence, so that it
     * can be included.
     *
     * When searching the "config", "messages", or "i18n" directories, or when
     * the `$array` flag is set to true, an array of all the files that match
     * that path in the [Cascading Filesystem](ko7/files) will be returned.
     * These files will return arrays which must be merged together.
     *
     * @param string $dir directory name (views, i18n, classes, extensions, etc.)
     * @param string $file filename with subdirectory
     * @param string $ext extension to search for
     * @param boolean $array return an array of files?
     * @return  array   a list of files when $array is TRUE
     * @return  string  single file path
     */
    public static function find_file(string $dir, string $file, ?string $ext = NULL, bool $array = FALSE)
    {
        if ($ext === NULL) {
            // Use the default extension
            $ext = '.php';
        } elseif ($ext) {
            // Prefix the extension with a period
            $ext = ".{$ext}";
        } else {
            // Use no extension
            $ext = '';
        }

        // Create a partial path of the filename
        $path = $dir . DIRECTORY_SEPARATOR . $file . $ext;

        if (static::$caching === TRUE && isset(static::$_files[$path . ($array ? '_array' : '_path')])) {
            // This path has been cached
            return static::$_files[$path . ($array ? '_array' : '_path')];
        }

        if (static::$profiling === TRUE && class_exists('Profiler', FALSE)) {
            // Start a new benchmark
            $benchmark = Profiler::start('KO7', __FUNCTION__);
        }

        if ($array || in_array($dir, ['config', 'i18n', 'messages'])) {
            // Include paths must be searched in reverse
            $paths = array_reverse(static::$_paths);

            // Array of files that have been found
            $found = [];

            foreach ($paths as $direct) {
                if (is_file($direct . $path)) {
                    // This path has a file, add it to the list
                    $found[] = $direct . $path;
                }
            }
        } else {
            // The file has not been found yet
            $found = FALSE;

            // If still not found. Search through $_paths
            if (!$found) {
                foreach (static::$_paths as $direct) {
                    if (is_file($direct . $path)) {
                        // A path has been found
                        $found = $direct . $path;
                        // Stop searching
                        break;
                    }
                }
            }
        }

        if (static::$caching === TRUE) {
            // Add the path to the cache
            static::$_files[$path . ($array ? '_array' : '_path')] = $found;

            // Files have been changed
            static::$_files_changed = TRUE;
        }

        if (isset($benchmark)) {
            // Stop the benchmark
            Profiler::stop($benchmark);
        }

        return $found;
    }

    /**
     * Loads a file within a totally empty scope and returns the output:
     *
     * @param string $file
     *
     * @return mixed;
     */
    public static function load(string $file)
    {
        return include $file;
    }

    /**
     * PHP error handler, converts all errors into Error_Exceptions. This handler
     * respects error_reporting settings.
     *
     * @return  TRUE
     * @throws  \KO7\Error\Exception
     */
    public static function error_handler(int $code, string $error, ?string $file = NULL, ?int $line = NULL): bool
    {
        if (error_reporting() & $code) {
            // This error is not suppressed by current error reporting settings
            // Convert the error into an Error_Exception
            throw new \KO7\Error\Exception($error, NULL, $code, 0, $file, $line);
        }

        // Do not execute the PHP error handler
        return TRUE;
    }

    /**
     * Catches errors that are not caught by the error handler, such as E_PARSE.
     * @return  void
     */
    public static function shutdown_handler(): void
    {
        if (!static::$_init) {
            // Do not execute when not active
            return;
        }

        try {
            if (static::$caching === TRUE && static::$_files_changed === TRUE) {
                // Write the file path cache
                static::cache('KO7::find_file()', static::$_files);
            }
        } catch (\Exception $e) {
            // Pass the exception to the handler
            Exception::handler($e);
        }

        if (static::$errors && ($error = error_get_last()) && in_array($error['type'], static::$shutdown_errors, true)) {
            // Clean the output buffer
            ob_get_level() AND ob_clean();

            // Fake an exception for nice debugging
            Exception::handler(new \KO7\Error\Exception($error['message'], NULL, $error['type'], 0, $error['file'], $error['line']));

            // Shutdown now to avoid a "death loop"
            exit(1);
        }
    }

    /**
     * Generates a version string based on the variables defined above.
     *
     * @return string
     */
    public static function version(): string
    {
        return 'Koseven ' . static::VERSION . ' (' . static::CODENAME . ')';
    }

    /**
     * Call this within your function to mark it deprecated.
     *
     * @param string $since Version since this function shall be marked deprecated.
     * @param string $replacement [optional] replacement function to use instead
     */
    public static function deprecated(string $since, string $replacement = ''): void
    {
        // Get current debug backtrace
        $calling = debug_backtrace()[1];

        // Extract calling class and calling function
        $class = $calling['class'];
        $function = $calling['function'];

        // Build message
        $msg = 'Function "' . $function . '" inside class "' . $class . '" is deprecated since version ' . $since .
            ' and will be removed within the next major release.';

        // Check if replacement function is provided
        if ($replacement) {
            $msg .= ' Please consider replacing it with "' . $replacement . '".';
        }

        // Log the deprecation
        $log = static::$log;
        $log->add(Log::WARNING, $msg);
        $log->write();
    }
}
