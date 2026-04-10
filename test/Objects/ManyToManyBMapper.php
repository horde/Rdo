<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Objects;

use Horde_Rdo_Mapper;

class ManyToManyBMapper extends Horde_Rdo_Mapper
{
    protected $_table = 'test_manytomanyb';
    protected $_lazyRelationships = [];
}
