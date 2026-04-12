<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit;

use Horde\Rdo\All;
use Horde\Rdo\Any;
use Horde\Rdo\CompositeCriterion;
use Horde\Rdo\CriterionVisitor;
use Horde\Rdo\Field;
use Horde\Rdo\FieldCriterion;
use Horde\Rdo\Has;
use Horde\Rdo\LogicalOperator;
use Horde\Rdo\Not;
use Horde\Rdo\NotCriterion;
use Horde\Rdo\Operator;
use Horde\Rdo\Raw;
use Horde\Rdo\RawCriterion;
use Horde\Rdo\RelationCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldCriterion::class)]
#[CoversClass(CompositeCriterion::class)]
#[CoversClass(NotCriterion::class)]
#[CoversClass(RelationCriterion::class)]
#[CoversClass(RawCriterion::class)]
#[CoversClass(Field::class)]
#[CoversClass(All::class)]
#[CoversClass(Any::class)]
#[CoversClass(Not::class)]
#[CoversClass(Has::class)]
#[CoversClass(Raw::class)]
class CriterionTest extends TestCase
{
    // --- FieldCriterion / Field factory ---

    public function testFieldEquals(): void
    {
        $c = Field::equals('status', 'active');

        $this->assertInstanceOf(FieldCriterion::class, $c);
        $this->assertSame('status', $c->field);
        $this->assertSame(Operator::EQUALS, $c->operator);
        $this->assertSame('active', $c->value);
    }

    public function testFieldNotEquals(): void
    {
        $c = Field::notEquals('role', 'guest');
        $this->assertSame(Operator::NOT_EQUALS, $c->operator);
        $this->assertSame('guest', $c->value);
    }

    public function testFieldGreaterThan(): void
    {
        $c = Field::greaterThan('age', 25);
        $this->assertSame(Operator::GREATER_THAN, $c->operator);
        $this->assertSame(25, $c->value);
    }

    public function testFieldLessThan(): void
    {
        $c = Field::lessThan('price', 99.99);
        $this->assertSame(Operator::LESS_THAN, $c->operator);
    }

    public function testFieldGreaterOrEqual(): void
    {
        $c = Field::greaterOrEqual('score', 90);
        $this->assertSame(Operator::GREATER_OR_EQUAL, $c->operator);
    }

    public function testFieldLessOrEqual(): void
    {
        $c = Field::lessOrEqual('weight', 50);
        $this->assertSame(Operator::LESS_OR_EQUAL, $c->operator);
    }

    public function testFieldIn(): void
    {
        $values = [1, 2, 3];
        $c = Field::in('id', $values);
        $this->assertSame(Operator::IN, $c->operator);
        $this->assertSame($values, $c->value);
    }

    public function testFieldNotIn(): void
    {
        $c = Field::notIn('status', ['deleted', 'banned']);
        $this->assertSame(Operator::NOT_IN, $c->operator);
    }

    public function testFieldIsNull(): void
    {
        $c = Field::isNull('email');
        $this->assertSame(Operator::IS_NULL, $c->operator);
        $this->assertNull($c->value);
    }

    public function testFieldIsNotNull(): void
    {
        $c = Field::isNotNull('phone');
        $this->assertSame(Operator::IS_NOT_NULL, $c->operator);
    }

    public function testFieldContains(): void
    {
        $c = Field::contains('name', 'smith');
        $this->assertSame(Operator::LIKE, $c->operator);
        $this->assertSame('%smith%', $c->value);
    }

    public function testFieldStartsWith(): void
    {
        $c = Field::startsWith('name', 'John');
        $this->assertSame(Operator::LIKE, $c->operator);
        $this->assertSame('John%', $c->value);
    }

    public function testFieldBetween(): void
    {
        $c = Field::between('age', 18, 65);
        $this->assertSame(Operator::BETWEEN, $c->operator);
        $this->assertSame([18, 65], $c->value);
    }

    public function testFieldSearch(): void
    {
        $c = Field::search('description', 'horde groupware');
        $this->assertSame(Operator::SEARCH, $c->operator);
        $this->assertSame('horde groupware', $c->value);
    }

    // --- CompositeCriterion / All / Any ---

    public function testAllOf(): void
    {
        $a = Field::equals('status', 'active');
        $b = Field::greaterThan('age', 25);

        $c = All::of($a, $b);

        $this->assertInstanceOf(CompositeCriterion::class, $c);
        $this->assertSame(LogicalOperator::AND, $c->logic);
        $this->assertCount(2, $c->children);
        $this->assertSame($a, $c->children[0]);
        $this->assertSame($b, $c->children[1]);
    }

    public function testAnyOf(): void
    {
        $a = Field::equals('role', 'admin');
        $b = Field::equals('role', 'editor');

        $c = Any::of($a, $b);

        $this->assertInstanceOf(CompositeCriterion::class, $c);
        $this->assertSame(LogicalOperator::OR, $c->logic);
        $this->assertCount(2, $c->children);
    }

    public function testNestedComposites(): void
    {
        $criteria = All::of(
            Field::equals('status', 'active'),
            Any::of(
                Field::greaterThan('score', 90),
                Field::in('role', ['admin', 'editor']),
            ),
            Not::of(Field::isNull('email')),
        );

        $this->assertCount(3, $criteria->children);
        $this->assertInstanceOf(CompositeCriterion::class, $criteria->children[1]);
        $this->assertInstanceOf(NotCriterion::class, $criteria->children[2]);
    }

    // --- NotCriterion ---

    public function testNot(): void
    {
        $inner = Field::isNull('email');
        $c = Not::of($inner);

        $this->assertInstanceOf(NotCriterion::class, $c);
        $this->assertSame($inner, $c->inner);
    }

    // --- RelationCriterion / Has ---

    public function testHasRelation(): void
    {
        $c = Has::relation('orders');

        $this->assertInstanceOf(RelationCriterion::class, $c);
        $this->assertSame('orders', $c->relation);
        $this->assertNull($c->condition);
    }

    public function testHasRelationWithCondition(): void
    {
        $condition = Field::greaterThan('amount', 100);
        $c = Has::relation('orders', $condition);

        $this->assertSame($condition, $c->condition);
    }

    // --- RawCriterion / Raw ---

    public function testRawSql(): void
    {
        $c = Raw::sql('ST_DWithin(location, ST_MakePoint(?, ?), ?)', [1.5, 2.5, 100]);

        $this->assertInstanceOf(RawCriterion::class, $c);
        $this->assertSame('ST_DWithin(location, ST_MakePoint(?, ?), ?)', $c->expression);
        $this->assertSame([1.5, 2.5, 100], $c->params);
    }

    public function testRawSqlWithoutParams(): void
    {
        $c = Raw::sql('1=1');
        $this->assertSame([], $c->params);
    }

    // --- Visitor dispatch (accept) ---

    public function testFieldCriterionAcceptDispatchesToVisitField(): void
    {
        $visitor = $this->createMock(CriterionVisitor::class);
        $criterion = Field::equals('x', 1);

        $visitor->expects($this->once())
            ->method('visitField')
            ->with($criterion)
            ->willReturn('field_result');

        $result = $criterion->accept($visitor);
        $this->assertSame('field_result', $result);
    }

    public function testCompositeCriterionAcceptDispatchesToVisitComposite(): void
    {
        $visitor = $this->createMock(CriterionVisitor::class);
        $criterion = All::of(Field::equals('x', 1));

        $visitor->expects($this->once())
            ->method('visitComposite')
            ->with($criterion)
            ->willReturn('composite_result');

        $result = $criterion->accept($visitor);
        $this->assertSame('composite_result', $result);
    }

    public function testNotCriterionAcceptDispatchesToVisitNot(): void
    {
        $visitor = $this->createMock(CriterionVisitor::class);
        $criterion = Not::of(Field::isNull('x'));

        $visitor->expects($this->once())
            ->method('visitNot')
            ->with($criterion)
            ->willReturn('not_result');

        $result = $criterion->accept($visitor);
        $this->assertSame('not_result', $result);
    }

    public function testRelationCriterionAcceptDispatchesToVisitRelation(): void
    {
        $visitor = $this->createMock(CriterionVisitor::class);
        $criterion = Has::relation('orders');

        $visitor->expects($this->once())
            ->method('visitRelation')
            ->with($criterion)
            ->willReturn('relation_result');

        $result = $criterion->accept($visitor);
        $this->assertSame('relation_result', $result);
    }

    public function testRawCriterionAcceptDispatchesToVisitRaw(): void
    {
        $visitor = $this->createMock(CriterionVisitor::class);
        $criterion = Raw::sql('1=1');

        $visitor->expects($this->once())
            ->method('visitRaw')
            ->with($criterion)
            ->willReturn('raw_result');

        $result = $criterion->accept($visitor);
        $this->assertSame('raw_result', $result);
    }
}
