<?php

namespace Horde\Rdo\Objects;
use Horde_Rdo_Mapper;

class RelatedThingMapper extends Horde_Rdo_Mapper
{
    /**
     * Inflector doesn't support Horde-style tables yet
     */
     protected $_table = 'test_relatedthings';
}
