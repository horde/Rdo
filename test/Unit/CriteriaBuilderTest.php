<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit;

use Horde\Rdo\All;
use Horde\Rdo\CriteriaBuilder;
use Horde\Rdo\Direction;
use Horde\Rdo\Field;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CriteriaBuilder::class)]
class CriteriaBuilderTest extends TestCase
{
    public function testFromWrapsACriterion(): void
    {
        $criterion = Field::equals('status', 'active');
        $builder = CriteriaBuilder::from($criterion);

        $this->assertSame($criterion, $builder->criterion());
        $this->assertSame([], $builder->orderByList());
        $this->assertNull($builder->getLimit());
        $this->assertNull($builder->getOffset());
        $this->assertSame([], $builder->eagerRelations());
    }

    public function testOrderByIsImmutable(): void
    {
        $criterion = Field::equals('status', 'active');
        $original = CriteriaBuilder::from($criterion);
        $modified = $original->orderBy('name');

        $this->assertSame([], $original->orderByList());
        $this->assertCount(1, $modified->orderByList());
        $this->assertSame('name', $modified->orderByList()[0][0]);
        $this->assertSame(Direction::ASC, $modified->orderByList()[0][1]);
    }

    public function testOrderByDescending(): void
    {
        $builder = CriteriaBuilder::from(Field::equals('x', 1))
            ->orderBy('created', Direction::DESC);

        $this->assertSame(Direction::DESC, $builder->orderByList()[0][1]);
    }

    public function testMultipleOrderBy(): void
    {
        $builder = CriteriaBuilder::from(Field::equals('x', 1))
            ->orderBy('name')
            ->orderBy('created', Direction::DESC);

        $this->assertCount(2, $builder->orderByList());
        $this->assertSame('name', $builder->orderByList()[0][0]);
        $this->assertSame('created', $builder->orderByList()[1][0]);
    }

    public function testLimitIsImmutable(): void
    {
        $original = CriteriaBuilder::from(Field::equals('x', 1));
        $modified = $original->limit(20);

        $this->assertNull($original->getLimit());
        $this->assertSame(20, $modified->getLimit());
    }

    public function testOffsetIsImmutable(): void
    {
        $original = CriteriaBuilder::from(Field::equals('x', 1));
        $modified = $original->offset(40);

        $this->assertNull($original->getOffset());
        $this->assertSame(40, $modified->getOffset());
    }

    public function testWithIsImmutable(): void
    {
        $original = CriteriaBuilder::from(Field::equals('x', 1));
        $modified = $original->with('orders', 'company');

        $this->assertSame([], $original->eagerRelations());
        $this->assertSame(['orders', 'company'], $modified->eagerRelations());
    }

    public function testWithDeduplicates(): void
    {
        $builder = CriteriaBuilder::from(Field::equals('x', 1))
            ->with('orders')
            ->with('orders', 'company');

        $this->assertSame(['orders', 'company'], $builder->eagerRelations());
    }

    public function testFullChaining(): void
    {
        $builder = CriteriaBuilder::from(
            All::of(
                Field::equals('status', 'active'),
                Field::greaterThan('age', 25),
            ),
        )
            ->orderBy('name')
            ->orderBy('created', Direction::DESC)
            ->limit(20)
            ->offset(40)
            ->with('company');

        $this->assertCount(2, $builder->orderByList());
        $this->assertSame(20, $builder->getLimit());
        $this->assertSame(40, $builder->getOffset());
        $this->assertSame(['company'], $builder->eagerRelations());
    }
}
