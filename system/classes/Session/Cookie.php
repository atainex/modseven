<?php
/**
 * Cookie-based session class.
 *
 * @package    KO7
 * @category   Session
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace KO7\Session;

use \KO7\Session;

class Cookie extends Session
{

    /**
     * @param string $id session id
     * @return  string
     */
    protected function _read(?string $id = NULL): string
    {
        return $this->get($this->_name, NULL);
    }

    /**
     * @return  null
     */
    protected function _regenerate(): ?string
    {
        // Cookie sessions have no id
        return NULL;
    }

    /**
     * @return  bool
     */
    protected function _write(): bool
    {
        $session = $this->set($this->_name, (string)$this);
        return true;
    }

    /**
     * @return  bool
     */
    protected function _restart(): bool
    {
        return TRUE;
    }

    /**
     * @return  bool
     */
    protected function _destroy(): bool
    {
        $session = $this->delete($this->_name);
        return true;
    }

}
