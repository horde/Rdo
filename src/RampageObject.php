<?php
/**
 * Rampage is a data holding object without much behaviour
 * It is the default result of a request to Reader
 * 
 */
namespace Horde\Rdo;
/**
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Ralf Lang <lang@b1-systems.de>
 * @license  http://www.horde.org/licenses/bsd BSD
 * @category Horde
 * @package  Rdo
 */
class RampageObject implements Rampage
{
    /**
     * The Mapper instance associated with this Rdo object. The
     * Mapper takes care of all backend access.
     *
     * @see Mapper
     * @var Mapper
     */
    protected $mapper;

    /**
     * This object's fields.
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Constructor. Can be called directly by a programmer, or is
     * called in Mapper::map(). Takes an associative array
     * of initial object values.
     *
     * @param array $fields Initial values for the new object.
     *
     * @see Mapper::map()
     */
    public function __construct(array $fields = [], string $iterator = '')
    {
        $this->setFields($fields);
        $this->setIterator($iterator);
    }

    /**
     * When Rdo objects are cloned, unset the unique id that
     * identifies them so that they can be modified and saved to the
     * backend as new objects. If you don't really want a new object,
     * don't clone.
     */
    public function __clone()
    {
        // @TODO Support composite primary keys
        unset($this->{$this->getMapper()->primaryKey});

        // @TODO What about associated objects?
    }

    /**
     * Fetch fields that haven't yet been loaded. Lazy-loaded fields
     * and lazy-loaded relationships are handled this way. Once a
     * field is retrieved, it is cached in the $fields array so it
     * doesn't need to be fetched again.
     *
     * @param string $field The name of the field to access.
     *
     * @return mixed The value of $field or null.
     */
    public function __get(string $field)
    {
        // Honor any explicit getters.
        $fieldMethod = 'get' . Horde_String::ucfirst($field);
        // If an Rdo_Base subclass has a __call() method, is_callable
        // returns true on every method name, so use method_exists
        // instead.
        if (method_exists($this, $fieldMethod)) {
            return call_user_func(array($this, $fieldMethod));
        }

        if (isset($this->fields[$field])) {
            return $this->fields[$field];
        }

        $mapper = $this->getMapper();

        // Look for lazy fields first, then relationships.
        if (in_array($field, $mapper->lazyFields)) {
            // @TODO Support composite primary keys
            $query = new Horde_Rdo_Query($mapper);
            $query->setFields($field)
                   ->addTest($mapper->primaryKey, '=', $this->{$mapper->primaryKey});
            list($sql, $params) = $query->getQuery();
            $this->fields[$field] = $mapper->adapter->selectValue($sql, $params);;
            return $this->fields[$field];
        } elseif (isset($mapper->lazyRelationships[$field])) {
            $rel = $mapper->lazyRelationships[$field];
        } else {
            return null;
        }

        // Try to find the Mapper class for the object the
        // relationship is with, and fail if we can't.
        if (isset($rel['mapper'])) {
            if ($mapper->factory) {
                $m = $mapper->factory->create($rel['mapper']);
            } else {
            // @TODO - should be getting this instance from somewhere
            // else external, and not passing the adapter along
            // automatically.
                $m = new $rel['mapper']($mapper->adapter);
            }
        } else {
            $m = $mapper->tableToMapper($field);
            if (is_null($m)) {
                return null;
            }
        }

        // Based on the kind of relationship, fetch the appropriate
        // objects and fill the cache.
        switch ($rel['type']) {
        case Constants::ONE_TO_ONE:
        case Constants::MANY_TO_ONE:
            if (isset($rel['query'])) {
                $query = $this->_fillPlaceholders($rel['query']);
                $this->fields[$field] = $m->findOne($query);
            } elseif (!empty($this->{$rel['foreignKey']})) {
                $this->fields[$field] = $m->findOne($this->{$rel['foreignKey']});
                if (empty($this->fields[$field])) {
                    throw new RdoException('The referenced object with key ' . $this->{$rel['foreignKey']} . ' does not exist. Your data is inconsistent');
                }
            } else {
                $this->fields[$field] = null;
            }
            break;

        case Constants::ONE_TO_MANY:
            $this->fields[$field] = $m->find(array($rel['foreignKey'] => $this->{$rel['foreignKey']}));
            break;

        case Constants::MANY_TO_MANY:
            $key = $mapper->primaryKey;
            $query = new Query();
            $on = isset($rel['on']) ? $rel['on'] : $m->primaryKey;
            $query->addRelationship($field, array('mapper' => $mapper,
                                                  'table' => $rel['through'],
                                                  'type' => Constants::MANY_TO_MANY,
                                                  'query' => array("$m->table.$on" => new Query\Literal($rel['through'] . '.' . $on), $key => $this->$key)));
            $this->fields[$field] = $m->find($query);
            break;
        }

        return $this->fields[$field];
    }

    /**
     * Implements getter for ArrayAccess interface.
     *
     * @see __get()
     */
    public function offsetGet($field)
    {
        return $this->__get($field);
    }

    /**
     * Set a field's value.
     *
     * @param string $field The field to set
     * @param mixed $value The field's new value
     */
    public function __set($field, $value)
    {
        // Honor any explicit setters.
        $fieldMethod = 'set' . Horde_String::ucfirst($field);
        // If an Rdo_Base subclass has a __call() method, is_callable
        // returns true on every method name, so use method_exists
        // instead.
        if (method_exists($this, $fieldMethod)) {
            return call_user_func(array($this, $fieldMethod), $value);
        }

        $this->fields[$field] = $value;
    }

    /**
     * Implements setter for ArrayAccess interface.
     *
     * @see __set()
     */
    public function offsetSet($field, $value)
    {
        $this->__set($field, $value);
    }

    /**
     * Allow using isset($rdo->foo) to check for field or
     * relationship presence.
     *
     * @param string $field The field name to check existence of.
     */
    public function __isset($field)
    {
        $m = $this->getMapper();
        return isset($this->fields[$field])
            || isset($m->fields[$field])
            || isset($m->lazyFields[$field])
            || isset($m->relationships[$field])
            || isset($m->lazyRelationships[$field]);
    }

    /**
     * Implements isset() for ArrayAccess interface.
     *
     * @see __isset()
     */
    public function offsetExists($field)
    {
        return $this->__isset($field);
    }

    /**
     * Allow using unset($rdo->foo) to unset a basic
     * field. Relationships cannot be unset in this way.
     *
     * @param string $field The field name to unset.
     */
    public function __unset($field)
    {
        // @TODO Should unsetting a MANY_TO_MANY relationship remove
        // the relationship?
        unset($this->fields[$field]);
    }

    /**
     * Implements unset() for ArrayAccess interface.
     *
     * @see __unset()
     */
    public function offsetUnset($field)
    {
        $this->__unset($field);
    }

    /**
     * Set field values for the object
     *
     * @param array $fields Initial values for the new object.
     *
     * @see Mapper::map()
     */
    public function setFields($fields = [])
    {
        $this->fields = $fields;
    }

    /**
     * Implement the IteratorAggregate interface. Looping over an Rdo
     * object goes through each property of the object in turn.
     *
     * @return Iterator The Iterator instance.
     */
    public function getIterator()
    {
        return new $this->{iteratorClass}($this);
    }

    /**
     * Change the default iterator
     * 
     * @since Rdo 3.0
     */
    public function setIterator(string $iterator = '')
    {
        $this->iteratorClass = $iterator ?? DefaultIterator::class;
    }

    /**
     * Get a Mapper instance that can be used to manage this
     * object. The Mapper instance can come from a few places:
     *
     * - If the class <RdoClassName>Mapper exists, it will be used
     *   automatically.
     *
     * - Any Rdo instance created with Mapper::map() will have a
     *   $mapper object set automatically.
     *
     * - Subclasses can override getMapper() to return the correct
     *   mapper object.
     *
     * - The programmer can call $rdoObject->setMapper($mapper) to provide a
     *   mapper object.
     *
     * A RdoException will be thrown if none of these
     * conditions are met.
     *
     * @return Mapper The Mapper instance managing this object.
     */
    public function getMapper()
    {
        if (!$this->mapper) {
            $class = get_class($this) . 'Mapper';
            if (class_exists($class)) {
                $this->mapper = new $class();
            } else {
                throw new RdoException('No Mapper object found. Override getMapper() or define the ' . get_class($this) . 'Mapper class.');
            }
        }

        return $this->mapper;
    }

    /**
     * Associate this Rdo object with the Mapper instance that will
     * manage it. Called automatically by Mapper:map().
     *
     * @param Mapper $mapper The Mapper to manage this Rdo object.
     *
     * @see Mapper::map()
     */
    public function setMapper($mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Adds a relation to one of the relationships defined in the mapper.
     *
     * - For one-to-one relations, simply updates the relation field.
     * - For one-to-many relations, updates the related object's relation field.
     * - For many-to-many relations, adds an entry in the "through" table.
     * - Performs a no-op if the peer is already related.
     *
     * This is a proxy to the mapper's addRelation() method.
     *
     * @param string $relationship  The relationship key in the mapper.
     * @param Rampage $peer  The object to add the relation.
     *
     * @throws RdoException
     */
    public function addRelation($relationship, Rampage $peer)
    {
        $this->mapper->addRelation($relationship, $this, $peer);
    }

    /**
     * Checks whether a relation to a peer is defined through one of the
     * relationships in the mapper.
     *
     * @param string $relationship  The relationship key in the mapper.
     * @param Rampage $peer  The object to check for the relation.
     *                              If this is null, check if there is any peer
     *                              for this relation.
     *
     * @return boolean  True if related.
     * @throws RdoException
     */
    public function hasRelation($relationship, Rampage $peer = null)
    {
        $mapper = $this->getMapper();
        if (isset($mapper->relationships[$relationship])) {
            $rel = $mapper->relationships[$relationship];
        } elseif (isset($mapper->lazyRelationships[$relationship])) {
            $rel = $mapper->lazyRelationships[$relationship];
        } else {
            throw new RdoException('The requested relation is not defined in the mapper');
        }

        $result = $this->$relationship;

        switch ($rel['type']) {
        case Constants::ONE_TO_ONE:
        case Constants::MANY_TO_ONE:
            if (empty($peer) || empty($result)) {
                return (bool) $result;
            }
            $key = $result->mapper->primaryKey;
            return $result->$key == $peer->$key;

        case Constants::ONE_TO_MANY:
        case Constants::MANY_TO_MANY:
            if (empty($peer)) {
                return (bool) count($result);
            }
            $key = $peer->mapper->primaryKey;
            foreach ($result as $item) {
                if ($item->$key == $peer->$key) {
                    return true;
                }
            }
            break;
        }

        return false;
    }

    /**
     * Removes a relation to one of the relationships defined in the mapper.
     *
     * - For one-to-one and one-to-many relations, simply sets the relation
     *   field to 0.
     * - For many-to-many, either deletes all relations to this object or just
     *   the relation to a given peer object.
     * - Performs a no-op if the peer is already unrelated.
     *
     * This is a proxy to the mapper's removeRelation method.
     *
     * @param string $relationship  The relationship key in the mapper
     * @param Rampage $peer  The object to remove from the relation
     * @return integer  The number of relations affected
     * @throws RdoException
     */
    public function removeRelation(string $relationship, Rampage $peer = null): integer
    {
        return $this->mapper->removeRelation($relationship, $this, $peer);
    }

    /**
     * Take a query array and replace @field@ placeholders with values
     * from this object.
     *
     * @param array $query The query to process placeholders on.
     *
     * @return array The query with placeholders filled in.
     */
    protected function _fillPlaceholders($query)
    {
        foreach (array_keys($query) as $field) {
            $value = $query[$field];
            if (preg_match('/^@(.*)@$/', $value, $matches)) {
                $query[$field] = $this->{$matches[1]};
            }
        }

        return $query;
    }

    /**
     * Converts the entity to an Array.
     *
     * This method can be used when handing it over to Horde_Variables so that
     * the database is not unnecessarily queried because of
     * lazyFields/lazyRelationships.
     *
     * @since Rdo 2.1.0
     *
     * @param bool $lazy           Whether lazy elements should be added.
     * @param bool $relationships  Whether relationships should be added.
     *
     * @return array  All selected fields and relationships.
     */
    public function toArray(bool $lazy = false, bool $relationships = false)
    {
        $array = [];

        $m = $this->getMapper();

        foreach ($m->fields as $field) {
            $array[$field] = $this->$field;
        }

        if ($lazy) {
            foreach ($m->lazyFields as $field) {
                $array[$field] = $this->$field;
            }
        }

        if ($relationships) {
            foreach (array_keys($m->relationships) as $rel) {
                $array[$rel] = $this->$rel;
            }
        }

        if ($lazy && $relationships) {
            foreach (array_keys($m->lazyRelationships) as $rel) {
                $array[$rel] = $this->$rel;
            }
        }

        return $array;
    }
    
}