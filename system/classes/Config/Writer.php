<?php

/**
 * Interface for config writers
 *
 * Specifies the methods that a config writer must implement
 *
 * @package KO7
 * ohana Team
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace KO7\Config;

interface Writer extends Source
{
    /**
     * Writes the passed config for $group
     *
     * Returns chainable instance on success or throws
     * KO7_Config_Exception on failure
     *
     * @param string $group The config group
     * @param string $key The config key to write to
     * @param array $config The configuration to write
     * @return boolean
     */
    public function write(string $group, string $key, array $config): bool;

}
