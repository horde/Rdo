<?php
/**
 * Rampage Factory: Creator of entities and mappers
 *
 * PHP version 7
 *
 * @category Horde
 * @package  Test
 * @author   Ralf Lang <lang@b1-systems.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 */
namespace Horde\Rdo;
use \Horde_Db_Adapter;

/**
 * The Factory is a caching root object for Mapper instances
 * This should itself be injected into applications by an injector
 *
 * @category Horde
 * @package Rdo
 */
class Factory
{
    /**
     * The list of already loaded Mapper classes
     * @var array
     */
    protected $_mappers = array();

    /**
     * The database connection to pass to the Mapper classes
     * @var Horde_Db_Adapter
     */
    protected $_adapter;

    /**
     * Constructor.
     *
     * @param Horde_Db_Adapter $adapter  A database adapter.
     * @return Factory  The Factory
     */
    public function __construct(Horde_Db_Adapter $adapter)
    {
        $this->_adapter = $adapter;
    }

    /**
     * Counts the number of cached mappers.
     *
     * @return integer  The number of cached mappers.
     */
    public function count()
    {
        return count($this->_mappers);
    }

    /**
     * Return the mapper instance.
     *
     * @param string $class              The mapper class.
     * @param Horde_Db_Adapter $adapter  A database adapter.
     *
     * @return Mapper  The Mapper descendant instance.
     * @throws RdoException
     */
    public function create($class, Horde_Db_Adapter $adapter = null)
    {
        if (!empty($this->_mappers[$class])) {
            return $this->_mappers[$class];
        }
        if (!class_exists($class)) {
            throw new RdoException(sprintf('Class %s not found', $class));
        }
        if (!$adapter) {
            $adapter = $this->_adapter;
        }
        $this->_mappers[$class] = new $class($adapter);
        return $this->_mappers[$class]->setFactory($this);
    }
}
