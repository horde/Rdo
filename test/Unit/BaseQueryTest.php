<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit;

use Horde\Rdo\BaseQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BaseQuery::class)]
class BaseQueryTest extends TestCase
{
    public function testCombineWithAND()
    {
        $q = new BaseQuery();
        $q->combineWith('AND');
        $this->assertSame('AND', $q->conjunction);
    }
}
