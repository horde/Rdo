<?php

namespace Horde\Rdo\Objects;
use Horde_Rdo_Mapper;
use Horde_Rdo;

class ManyToManyBMapper extends Horde_Rdo_Mapper
{
    /**
     * Inflector doesn't support Horde-style tables yet
     */
    protected $_table = 'test_manytomanyb';
    protected $_lazyRelationships = array();
}
