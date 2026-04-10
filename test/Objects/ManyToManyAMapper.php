<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Objects;

use Horde_Rdo;
use Horde_Rdo_Mapper;

class ManyToManyAMapper extends Horde_Rdo_Mapper
{
    protected $_table = 'test_manytomanya';
    protected $_lazyRelationships = [
        'manybs' => [
            'type' => Horde_Rdo::MANY_TO_MANY,
            'through' => 'test_manythrough',
            'mapper' => ManyToManyBMapper::class,
        ],
    ];
}
