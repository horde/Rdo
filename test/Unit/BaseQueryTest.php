<?php

namespace Horde\Rdo\Unit\Test;

use Horde\Test\TestCase;

use Horde\Rdo\BaseQuery;

class BaseQueryTest extends TestCase
{
    public function testCombineWithAND()
    {
        $q = new BaseQuery();
        $q->combineWith('AND');
        $this->assertSame('AND', $q->conjunction);
    }
}
