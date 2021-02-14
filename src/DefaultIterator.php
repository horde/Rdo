<?php
/**
 * @category Horde
 * @package Rdo
 */
namespace Horde\Rdo;
/**
 * Iterator for Horde_Rdo_Base objects that allows relationships and
 * decorated objects to be handled gracefully.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Ralf Lang <lang@b1-systems.de>
 * @license  http://www.horde.org/licenses/bsd BSD
 * @category Horde
 * @package  Rdo
 */
class DefaultIterator implements \Iterator {

    /**
     * @var Rampage
     */
    private $rampage;

    /**
     * List of keys that we'll iterator over. This is the combined
     * list of the fields, lazyFields, relationships, and
     * lazyRelationships properties from the objects Horde_Rdo_Mapper.
     */
    private $keys = [];

    /**
     * Current index
     *
     * @var mixed
     */
    private $index = null;

    /**
     * Are we inside the array bounds?
     *
     * @var boolean
     */
    private $valid = false;

    /**
     * New Horde_Rdo_Iterator for iterating over Rdo objects.
     *
     * @param Rampage $rdo The object to iterate over
     */
    public function __construct(Rampage $rampage)
    {
        $this->rampage = $rampage;

        $this->keys = array_merge(
            $rampage->getEagerFields(),
            $rampage->getLazyFields(),
            $rampage->getRelationships(),
            $rampage->getLazyRelationships());
    }

    /**
     * Reset to the first key.
     */
    public function rewind()
    {
        $this->_valid = (false !== reset($this->_keys));
    }

    /**
     * Return the current value.
     *
     * @return mixed The current value
     */
    public function current()
    {
        $key = $this->key();
        return $this->rampage->getValue($key);
    }

    /**
     * Return the current key.
     *
     * @return mixed The current key
     */
    public function key()
    {
        return current($this->keys);
    }

    /**
     * Move to the next key in the iterator.
     */
    public function next()
    {
        $this->valid = (false !== next($this->keys));
    }

    /**
     * Check array bounds.
     *
     * @return boolean Inside array bounds?
     */
    public function valid()
    {
        return $this->valid;
    }

}
