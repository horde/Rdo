<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Objects;

use Horde_Rdo;
use Horde_Rdo_Mapper;

class SomeLazyBaseObjectMapper extends Horde_Rdo_Mapper
{
    protected $_table = 'test_somelazybaseobjects';
    protected $_lazyRelationships = [
        'lazyRelatedThing' => [
            'type' => Horde_Rdo::ONE_TO_ONE,
            'foreignKey' => 'relatedthing_id',
            'mapper' => RelatedThingMapper::class,
        ],
    ];
}
