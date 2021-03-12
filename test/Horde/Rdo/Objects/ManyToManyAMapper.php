<?php

namespace Horde\Rdo\Objects;
use Horde_Rdo_Mapper;
use Horde_Rdo;

class ManyToManyAMapper extends Horde_Rdo_Mapper
{
    /**
     * Inflector doesn't support Horde-style tables yet
     */
    protected $_table = 'test_manytomanya';
    protected $_lazyRelationships = array(
        'manybs' => array(
            'type' => Horde_Rdo::MANY_TO_MANY,
            'through' => 'test_manythrough',
            'mapper' => 'Horde\Rdo\Objects\ManyToManyBMapper'));
}
