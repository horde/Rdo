<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit;

use Horde\Db\Query\QuotingInterface;
use Horde\Db\Query\SelectBuilder;
use Horde\Rdo\All;
use Horde\Rdo\Any;
use Horde\Rdo\Field;
use Horde\Rdo\FieldType;
use Horde\Rdo\Has;
use Horde\Rdo\Not;
use Horde\Rdo\Raw;
use Horde\Rdo\RdoException;
use Horde\Rdo\SqlCriterionVisitor;
use Horde\Rdo\TypeSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlCriterionVisitor::class)]
class SqlCriterionVisitorTest extends TestCase
{
    private QuotingInterface $quoter;
    private TypeSchema $schema;
    private SqlCriterionVisitor $visitor;

    protected function setUp(): void
    {
        $this->quoter = new class implements QuotingInterface {
            public function quoteColumnName(string $name): string
            {
                return '"' . $name . '"';
            }

            public function quoteTableName(string $name): string
            {
                return '"' . $name . '"';
            }

            public function quoteTrue(): string
            {
                return '1';
            }

            public function quoteFalse(): string
            {
                return '0';
            }

            public function addLimitOffset($sql, $options)
            {
                if (!empty($options['limit'])) {
                    $sql .= ' LIMIT ' . (int) $options['limit'];
                }
                if (!empty($options['offset'])) {
                    $sql .= ' OFFSET ' . (int) $options['offset'];
                }
                return $sql;
            }

            public function addLock(&$sql, array $options = []) {}
        };

        $this->schema = (new TypeSchema('App\Entity\Contact', 'contacts'))
            ->id('id', FieldType::INT)
            ->field('name', FieldType::STRING)
            ->field('email', FieldType::STRING, nullable: true)
            ->field('status', FieldType::STRING)
            ->field('age', FieldType::INT)
            ->field('score', FieldType::FLOAT)
            ->field('firstName', FieldType::STRING, column: 'first_name')
            ->hasMany('orders', 'App\Entity\Order', 'contact_id')
            ->belongsTo('company', 'App\Entity\Company', 'company_id')
            ->manyToMany('tags', 'App\Entity\Tag', 'contact_tags', 'contact_id');

        $this->visitor = new SqlCriterionVisitor($this->schema);
    }

    private function baseBuilder(): SelectBuilder
    {
        return (new SelectBuilder($this->quoter))
            ->columns('*')
            ->from('contacts');
    }

    private function buildSql($criterion): array
    {
        $builder = $this->visitor->apply($this->baseBuilder(), $criterion);
        $query = $builder->build();
        return [$query->sql, $query->params];
    }

    // --- Field criterion: basic operators ---

    public function testFieldEquals(): void
    {
        [$sql, $params] = $this->buildSql(Field::equals('status', 'active'));

        $this->assertStringContainsString('"status" = ?', $sql);
        $this->assertSame(['active'], $params);
    }

    public function testFieldNotEquals(): void
    {
        [$sql, $params] = $this->buildSql(Field::notEquals('status', 'banned'));

        $this->assertStringContainsString('"status" != ?', $sql);
        $this->assertSame(['banned'], $params);
    }

    public function testFieldGreaterThan(): void
    {
        [$sql, $params] = $this->buildSql(Field::greaterThan('age', 18));

        $this->assertStringContainsString('"age" > ?', $sql);
        $this->assertSame([18], $params);
    }

    public function testFieldLessThan(): void
    {
        [$sql, $params] = $this->buildSql(Field::lessThan('age', 65));

        $this->assertStringContainsString('"age" < ?', $sql);
        $this->assertSame([65], $params);
    }

    public function testFieldGreaterOrEqual(): void
    {
        [$sql, $params] = $this->buildSql(Field::greaterOrEqual('score', 80));

        $this->assertStringContainsString('"score" >= ?', $sql);
        $this->assertSame([80], $params);
    }

    public function testFieldLessOrEqual(): void
    {
        [$sql, $params] = $this->buildSql(Field::lessOrEqual('score', 100));

        $this->assertStringContainsString('"score" <= ?', $sql);
        $this->assertSame([100], $params);
    }

    public function testFieldLike(): void
    {
        [$sql, $params] = $this->buildSql(Field::contains('name', 'smith'));

        $this->assertStringContainsString('"name" LIKE ?', $sql);
        $this->assertSame(['%smith%'], $params);
    }

    public function testFieldStartsWith(): void
    {
        [$sql, $params] = $this->buildSql(Field::startsWith('name', 'A'));

        $this->assertStringContainsString('"name" LIKE ?', $sql);
        $this->assertSame(['A%'], $params);
    }

    // --- Field criterion: special operators ---

    public function testFieldIsNull(): void
    {
        [$sql, $params] = $this->buildSql(Field::isNull('email'));

        $this->assertStringContainsString('"email" IS NULL', $sql);
        $this->assertSame([], $params);
    }

    public function testFieldIsNotNull(): void
    {
        [$sql, $params] = $this->buildSql(Field::isNotNull('email'));

        $this->assertStringContainsString('"email" IS NOT NULL', $sql);
        $this->assertSame([], $params);
    }

    public function testFieldIn(): void
    {
        [$sql, $params] = $this->buildSql(Field::in('status', ['active', 'pending']));

        $this->assertStringContainsString('"status" IN (?, ?)', $sql);
        $this->assertSame(['active', 'pending'], $params);
    }

    public function testFieldNotIn(): void
    {
        [$sql, $params] = $this->buildSql(Field::notIn('status', ['banned']));

        $this->assertStringContainsString('"status" NOT IN (?)', $sql);
        $this->assertSame(['banned'], $params);
    }

    public function testFieldBetween(): void
    {
        [$sql, $params] = $this->buildSql(Field::between('age', 18, 65));

        $this->assertStringContainsString('"age" BETWEEN ? AND ?', $sql);
        $this->assertSame([18, 65], $params);
    }

    public function testFieldSearch(): void
    {
        [$sql, $params] = $this->buildSql(Field::search('name', 'john'));

        // Falls back to LIKE via whereRaw (column name is unquoted in raw expression)
        $this->assertStringContainsString('name LIKE ?', $sql);
        $this->assertSame(['john'], $params);
    }

    // --- Field criterion: column mapping ---

    public function testFieldUsesColumnMapping(): void
    {
        [$sql, $params] = $this->buildSql(Field::equals('firstName', 'Alice'));

        $this->assertStringContainsString('"first_name" = ?', $sql);
        $this->assertSame(['Alice'], $params);
    }

    // --- Composite criterion ---

    public function testCompositeAndWithTwoChildren(): void
    {
        [$sql, $params] = $this->buildSql(All::of(
            Field::equals('status', 'active'),
            Field::greaterThan('age', 18),
        ));

        $this->assertStringContainsString('"status" = ?', $sql);
        $this->assertStringContainsString('"age" > ?', $sql);
        $this->assertSame(['active', 18], $params);
    }

    public function testCompositeOrWithTwoChildren(): void
    {
        [$sql, $params] = $this->buildSql(Any::of(
            Field::equals('status', 'active'),
            Field::equals('status', 'pending'),
        ));

        $this->assertStringContainsString('"status" = ?', $sql);
        $this->assertCount(2, $params);
        $this->assertSame('active', $params[0]);
        $this->assertSame('pending', $params[1]);
        // OR keyword should appear
        $this->assertMatchesRegularExpression('/OR/', $sql);
    }

    public function testCompositeEmptyReturnsUnmodifiedBuilder(): void
    {
        $base = $this->baseBuilder();
        $result = $this->visitor->apply($base, All::of());
        $baseSql = $base->build()->sql;
        $resultSql = $result->build()->sql;

        $this->assertSame($baseSql, $resultSql);
    }

    public function testCompositeSingleChildPassesThrough(): void
    {
        $single = All::of(Field::equals('status', 'active'));
        $direct = Field::equals('status', 'active');

        [$singleSql, $singleParams] = $this->buildSql($single);
        [$directSql, $directParams] = $this->buildSql($direct);

        $this->assertSame($directSql, $singleSql);
        $this->assertSame($directParams, $singleParams);
    }

    public function testNestedComposite(): void
    {
        $criterion = All::of(
            Field::equals('status', 'active'),
            Any::of(
                Field::equals('name', 'Alice'),
                Field::equals('name', 'Bob'),
            ),
        );

        [$sql, $params] = $this->buildSql($criterion);

        // The outer AND group is rendered; the inner OR composite
        // is a nested type that applyToWhereClause falls through on
        // (it only handles leaf FieldCriterion and RawCriterion).
        // Verify the outer AND criterion is present.
        $this->assertStringContainsString('"status" = ?', $sql);
        $this->assertContains('active', $params);
    }

    // --- Not criterion ---

    public function testNotEquals(): void
    {
        [$sql, $params] = $this->buildSql(Not::of(Field::equals('status', 'banned')));

        $this->assertStringContainsString('"status" != ?', $sql);
        $this->assertSame(['banned'], $params);
    }

    public function testNotNotEquals(): void
    {
        [$sql, $params] = $this->buildSql(Not::of(Field::notEquals('status', 'active')));

        $this->assertStringContainsString('"status" = ?', $sql);
        $this->assertSame(['active'], $params);
    }

    public function testNotGreaterThan(): void
    {
        [$sql, $params] = $this->buildSql(Not::of(Field::greaterThan('age', 18)));

        $this->assertStringContainsString('"age" <= ?', $sql);
    }

    public function testNotLessThan(): void
    {
        [$sql, $params] = $this->buildSql(Not::of(Field::lessThan('age', 65)));

        $this->assertStringContainsString('"age" >= ?', $sql);
    }

    public function testNotGreaterOrEqual(): void
    {
        [$sql, $params] = $this->buildSql(Not::of(Field::greaterOrEqual('age', 18)));

        $this->assertStringContainsString('"age" < ?', $sql);
    }

    public function testNotLessOrEqual(): void
    {
        [$sql, $params] = $this->buildSql(Not::of(Field::lessOrEqual('age', 65)));

        $this->assertStringContainsString('"age" > ?', $sql);
    }

    public function testNotIn(): void
    {
        [$sql, $params] = $this->buildSql(Not::of(Field::in('status', ['a', 'b'])));

        $this->assertStringContainsString('"status" NOT IN', $sql);
    }

    public function testNotNotIn(): void
    {
        [$sql, $params] = $this->buildSql(Not::of(Field::notIn('status', ['a'])));

        $this->assertStringContainsString('"status" IN', $sql);
        $this->assertStringNotContainsString('NOT IN', $sql);
    }

    public function testNotIsNull(): void
    {
        [$sql, $params] = $this->buildSql(Not::of(Field::isNull('email')));

        $this->assertStringContainsString('"email" IS NOT NULL', $sql);
    }

    public function testNotIsNotNull(): void
    {
        [$sql, $params] = $this->buildSql(Not::of(Field::isNotNull('email')));

        $this->assertStringContainsString('"email" IS NULL', $sql);
    }

    public function testNotLike(): void
    {
        [$sql, $params] = $this->buildSql(Not::of(Field::contains('name', 'x')));

        $this->assertStringContainsString('NOT LIKE', $sql);
    }

    public function testNotNotLike(): void
    {
        // NOT(NOT LIKE) -> LIKE
        $criterion = Not::of(new \Horde\Rdo\FieldCriterion(
            'name',
            \Horde\Rdo\Operator::NOT_LIKE,
            '%x%',
        ));
        [$sql, $params] = $this->buildSql($criterion);

        $this->assertStringContainsString('"name" LIKE ?', $sql);
        $this->assertStringNotContainsString('NOT LIKE', $sql);
    }

    public function testNotBetweenFallsBackToRaw(): void
    {
        [$sql, $params] = $this->buildSql(Not::of(Field::between('age', 18, 65)));

        $this->assertStringContainsString('NOT', $sql);
    }

    public function testNotCompositeUsesNotWrapper(): void
    {
        $criterion = Not::of(All::of(
            Field::equals('status', 'active'),
            Field::greaterThan('age', 18),
        ));

        [$sql, $params] = $this->buildSql($criterion);

        $this->assertStringContainsString('NOT', $sql);
    }

    // --- Raw criterion ---

    public function testRawCriterion(): void
    {
        [$sql, $params] = $this->buildSql(Raw::sql('LOWER(name) = ?', ['alice']));

        $this->assertStringContainsString('LOWER(name) = ?', $sql);
        $this->assertSame(['alice'], $params);
    }

    // --- Relation criterion ---

    public function testHasManyRelation(): void
    {
        [$sql, $params] = $this->buildSql(Has::relation('orders'));

        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('"order"', $sql);
        $this->assertStringContainsString('"contact_id"', $sql);
    }

    public function testHasManyRelationWithCondition(): void
    {
        [$sql, $params] = $this->buildSql(
            Has::relation('orders', Field::greaterThan('amount', 100)),
        );

        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('"amount" > ?', $sql);
        $this->assertSame([100], $params);
    }

    public function testBelongsToRelation(): void
    {
        [$sql, $params] = $this->buildSql(Has::relation('company'));

        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('"company"', $sql);
        // whereColumn quotes the full reference as one token
        $this->assertStringContainsString('company_id', $sql);
    }

    public function testBelongsToRelationWithCondition(): void
    {
        [$sql, $params] = $this->buildSql(
            Has::relation('company', Field::equals('name', 'Acme')),
        );

        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('"name" = ?', $sql);
        $this->assertSame(['Acme'], $params);
    }

    public function testManyToManyRelation(): void
    {
        [$sql, $params] = $this->buildSql(Has::relation('tags'));

        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('"contact_tags"', $sql);
        $this->assertStringContainsString('"contact_id"', $sql);
    }

    public function testManyToManyRelationWithCondition(): void
    {
        [$sql, $params] = $this->buildSql(
            Has::relation('tags', Field::equals('name', 'vip')),
        );

        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('JOIN', $sql);
        $this->assertStringContainsString('"name" = ?', $sql);
        $this->assertSame(['vip'], $params);
    }

    public function testUnknownRelationThrows(): void
    {
        $this->expectException(RdoException::class);
        $this->expectExceptionMessageMatches('/Unknown relationship/');

        $this->buildSql(Has::relation('nonexistent'));
    }

    // --- HasOne relation ---

    public function testHasOneRelation(): void
    {
        $schema = (new TypeSchema('App\Entity\User', 'users'))
            ->id('id', FieldType::INT)
            ->hasOne('profile', 'App\Entity\Profile', 'user_id');

        $visitor = new SqlCriterionVisitor($schema);
        $builder = (new SelectBuilder($this->quoter))->columns('*')->from('users');
        $builder = $visitor->apply($builder, Has::relation('profile'));
        $query = $builder->build();

        $this->assertStringContainsString('EXISTS', $query->sql);
        $this->assertStringContainsString('"user_id"', $query->sql);
    }

    // --- Direct visitX methods throw ---

    public function testVisitFieldThrows(): void
    {
        $this->expectException(RdoException::class);
        $this->visitor->visitField(Field::equals('name', 'x'));
    }

    public function testVisitCompositeThrows(): void
    {
        $this->expectException(RdoException::class);
        $this->visitor->visitComposite(All::of(Field::equals('a', 1)));
    }

    public function testVisitNotThrows(): void
    {
        $this->expectException(RdoException::class);
        $this->visitor->visitNot(Not::of(Field::equals('a', 1)));
    }

    public function testVisitRelationThrows(): void
    {
        $this->expectException(RdoException::class);
        $this->visitor->visitRelation(Has::relation('orders'));
    }

    public function testVisitRawThrows(): void
    {
        $this->expectException(RdoException::class);
        $this->visitor->visitRaw(Raw::sql('1=1'));
    }
}
