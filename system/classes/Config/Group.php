<?php

/**
 * The group wrapper acts as an interface to all the config directives
 * gathered from across the system.
 *
 * This is the object returned from KO7_Config::load
 *
 * Any modifications to configuration items should be done through an instance of this object
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license        https://koseven.ga/LICENSE
 *
 * @package        KO7\Config
 */

namespace KO7\Config;

use \KO7\Config;
use ArrayObject;

class Group extends ArrayObject
{

    /**
     * Reference the config object that created this group
     * Used when updating config
     *
     * @var Config
     */
    protected $_parent_instance = null;

    /**
     * The group this config is for
     * Used when updating config items
     *
     * @var string
     */
    protected $_group_name = '';

    /**
     * Constructs the group object.  KO7_Config passes the config group
     * and its config items to the object here.
     *
     * @param Config $instance "Owning" instance of KO7_Config
     * @param string $group The group name
     * @param array $config Group's config
     */
    public function __construct(Config $instance, string $group, array $config = [])
    {
        $this->_parent_instance = $instance;
        $this->_group_name = $group;

        parent::__construct($config, ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Return the current group in serialized form
     *
     * @return  string
     */
    public function __toString(): string
    {
        return serialize($this->getArrayCopy());
    }

    /**
     * Alias for getArrayCopy()
     *
     * @return array Array copy of the group's config
     */
    public function as_array(): array
    {
        return $this->getArrayCopy();
    }

    /**
     * Returns the config group's name
     *
     * @return string The group name
     */
    public function group_name(): string
    {
        return $this->_group_name;
    }

    /**
     * Get a variable from the configuration or return the default value.
     *
     *     $value = $config->get($key);
     *
     * @param string $key array key
     * @param mixed $default default value
     *
     * @return  mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->offsetExists($key) ? $this->offsetGet($key) : $default;
    }

    /**
     * Sets a value in the configuration array.
     *
     *     $config->set($key, $new_value);
     *
     * @param string $key array key
     * @param mixed $value array value
     *
     * @return  self
     */
    public function set(string $key, $value): self
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    /**
     * Overrides ArrayObject::offsetSet()
     *
     * @param string $key The key of the config item we're changing
     * @param mixed $value The new array value
     */
    public function offsetSet($key, $value): void
    {
        $this->_parent_instance->_write_config($this->_group_name, $key, $value);

        parent::offsetSet($key, $value);
    }

}
