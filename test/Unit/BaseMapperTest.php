<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit;

use Countable;
use Horde\Db\Adapter;
use Horde\Db\Query\QuotingInterface;
use Horde\Rdo\Base;
use Horde\Rdo\BaseMapper;
use Horde\Rdo\Constants;
use Horde\Rdo\CriteriaBuilder;
use Horde\Rdo\Criterion;
use Horde\Rdo\Field;
use Horde\Rdo\FieldType;
use Horde\Rdo\MapperSql;
use Horde\Rdo\RdoException;
use Horde\Rdo\RelationType;
use Horde\Rdo\TypeSchema;
use Horde_Db_Adapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BaseMapper::class)]
#[CoversClass(MapperSql::class)]
class BaseMapperTest extends TestCase
{
    // --- getTypeSchema: basic fields ---

    public function testGetTypeSchemaReturnsTypeSchema(): void
    {
        $mapper = new TestContactMapper(new MapperStubAdapter());

        $schema = $mapper->getTypeSchema();

        $this->assertInstanceOf(TypeSchema::class, $schema);
    }

    public function testTypeSchemaReflectsTable(): void
    {
        $mapper = new TestContactMapper(new MapperStubAdapter());

        $this->assertSame('contacts', $mapper->getTypeSchema()->getTable());
    }

    public function testTypeSchemaReflectsPrimaryKey(): void
    {
        $mapper = new TestContactMapper(new MapperStubAdapter());

        $pk = $mapper->getTypeSchema()->getPrimaryKey();
        $this->assertNotNull($pk);
        $this->assertSame('contact_id', $pk->name);
        $this->assertTrue($pk->primary);
        $this->assertSame(FieldType::INT, $pk->type);
    }

    public function testTypeSchemaReflectsEagerFields(): void
    {
        $mapper = new TestContactMapper(new MapperStubAdapter());

        $schema = $mapper->getTypeSchema();
        $name = $schema->getField('name');
        $this->assertNotNull($name);
        $this->assertSame(FieldType::STRING, $name->type);
        $this->assertFalse($name->lazy);

        $email = $schema->getField('email');
        $this->assertNotNull($email);
    }

    public function testTypeSchemaReflectsLazyFields(): void
    {
        $mapper = new TestContactMapper(new MapperStubAdapter());

        $bio = $mapper->getTypeSchema()->getField('bio');
        $this->assertNotNull($bio);
        $this->assertTrue($bio->lazy);
    }

    public function testTypeSchemaReflectsTimestamps(): void
    {
        $mapper = new TestContactMapper(new MapperStubAdapter());

        $this->assertTrue($mapper->getTypeSchema()->hasTimestamps());
    }

    public function testTypeSchemaReflectsNoTimestamps(): void
    {
        $mapper = new TestNoTimestampMapper(new MapperStubAdapter());

        $this->assertFalse($mapper->getTypeSchema()->hasTimestamps());
    }

    // --- getTypeSchema: relationships ---

    public function testTypeSchemaReflectsHasManyRelation(): void
    {
        $mapper = new TestContactMapper(new MapperStubAdapter());

        $rel = $mapper->getTypeSchema()->getRelation('orders');
        $this->assertNotNull($rel);
        $this->assertSame(RelationType::HAS_MANY, $rel->type);
        $this->assertSame('OrderMapper', $rel->targetClass);
        $this->assertSame('contact_id', $rel->foreignKey);
    }

    public function testTypeSchemaReflectsBelongsToRelation(): void
    {
        $mapper = new TestContactMapper(new MapperStubAdapter());

        $rel = $mapper->getTypeSchema()->getRelation('company');
        $this->assertNotNull($rel);
        $this->assertSame(RelationType::BELONGS_TO, $rel->type);
        $this->assertSame('CompanyMapper', $rel->targetClass);
        $this->assertSame('company_id', $rel->foreignKey);
    }

    public function testTypeSchemaReflectsManyToManyRelation(): void
    {
        $mapper = new TestContactMapper(new MapperStubAdapter());

        $rel = $mapper->getTypeSchema()->getRelation('tags');
        $this->assertNotNull($rel);
        $this->assertSame(RelationType::MANY_TO_MANY, $rel->type);
        $this->assertSame('TagMapper', $rel->targetClass);
        $this->assertSame('contact_tags', $rel->through);
    }

    public function testTypeSchemaReflectsLazyRelation(): void
    {
        $mapper = new TestContactMapper(new MapperStubAdapter());

        // 'notes' is in $_lazyRelationships
        $rel = $mapper->getTypeSchema()->getRelation('notes');
        $this->assertNotNull($rel);
        $this->assertSame(RelationType::HAS_MANY, $rel->type);
    }

    public function testTypeSchemaCachesResult(): void
    {
        $mapper = new TestContactMapper(new MapperStubAdapter());

        $schema1 = $mapper->getTypeSchema();
        $schema2 = $mapper->getTypeSchema();

        $this->assertSame($schema1, $schema2);
    }

    // --- getTypeSchema: entity class ---

    public function testTypeSchemaReflectsEntityClass(): void
    {
        $mapper = new TestContactMapper(new MapperStubAdapter());

        $this->assertSame(TestContact::class, $mapper->getTypeSchema()->getEntityClass());
    }

    // --- MapperSql inherits behavior ---

    public function testMapperSqlSubclassGetsTypeSchema(): void
    {
        $mapper = new TestMapperSqlChild(new MapperStubAdapter());

        $schema = $mapper->getTypeSchema();
        $this->assertSame('items', $schema->getTable());
        $this->assertSame('item_id', $schema->getPrimaryKey()->name);
    }

    // --- findByCriteria ---

    public function testFindByCriteriaReturnsEntities(): void
    {
        $adapter = new MapperStubAdapter([
            'selectAll' => [
                ['contact_id' => '1', 'name' => 'Alice', 'email' => 'alice@x.com'],
                ['contact_id' => '2', 'name' => 'Bob', 'email' => null],
            ],
        ]);

        $mapper = new TestContactMapper($adapter);
        $results = $mapper->findByCriteria(Field::equals('name', 'Alice'));

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertInstanceOf(TestContact::class, $results[0]);
    }

    public function testFindByCriteriaPassesSql(): void
    {
        $adapter = new MapperStubAdapter(['selectAll' => []]);

        $mapper = new TestContactMapper($adapter);
        $mapper->findByCriteria(Field::equals('name', 'Alice'));

        $call = $adapter->lastCall('selectAll');
        $this->assertNotNull($call);
        $this->assertStringContainsString('"contacts"', $call['sql']);
        $this->assertStringContainsString('"name" = ?', $call['sql']);
        $this->assertSame(['Alice'], $call['params']);
    }

    public function testFindByCriteriaWithCriteriaBuilder(): void
    {
        $adapter = new MapperStubAdapter([
            'selectAll' => [
                ['contact_id' => '3', 'name' => 'Charlie', 'email' => 'c@x.com'],
            ],
        ]);

        $mapper = new TestContactMapper($adapter);
        $criteria = CriteriaBuilder::from(Field::equals('name', 'Charlie'))
            ->limit(10)
            ->offset(5);

        $results = $mapper->findByCriteria($criteria);

        $this->assertCount(1, $results);
    }

    public function testFindByCriteriaThrowsWithoutQuotingInterface(): void
    {
        $adapter = $this->createStub(Horde_Db_Adapter::class);

        $mapper = new TestContactMapperLegacyAdapter($adapter);

        $this->expectException(RdoException::class);
        $this->expectExceptionMessageMatches('/QuotingInterface/');

        $mapper->findByCriteria(Field::equals('name', 'Alice'));
    }

    // --- countByCriteria ---

    public function testCountByCriteriaReturnsCount(): void
    {
        $adapter = new MapperStubAdapter([
            'selectOne' => ['cnt' => '7'],
        ]);

        $mapper = new TestContactMapper($adapter);
        $count = $mapper->countByCriteria(Field::equals('name', 'Alice'));

        $this->assertSame(7, $count);
    }

    public function testCountByCriteriaPassesSql(): void
    {
        $adapter = new MapperStubAdapter(['selectOne' => ['cnt' => '0']]);

        $mapper = new TestContactMapper($adapter);
        $mapper->countByCriteria(Field::equals('name', 'Alice'));

        $call = $adapter->lastCall('selectOne');
        $this->assertNotNull($call);
        $this->assertStringContainsString('COUNT(*)', $call['sql']);
        $this->assertStringContainsString('"name" = ?', $call['sql']);
    }

    public function testCountByCriteriaThrowsWithoutQuotingInterface(): void
    {
        $adapter = $this->createStub(Horde_Db_Adapter::class);

        $mapper = new TestContactMapperLegacyAdapter($adapter);

        $this->expectException(RdoException::class);
        $this->expectExceptionMessageMatches('/QuotingInterface/');

        $mapper->countByCriteria(Field::equals('name', 'Alice'));
    }

    public function testCountByCriteriaWithCriteriaBuilder(): void
    {
        $adapter = new MapperStubAdapter(['selectOne' => ['cnt' => '3']]);

        $mapper = new TestContactMapper($adapter);
        $criteria = CriteriaBuilder::from(Field::equals('name', 'Alice'));

        $this->assertSame(3, $mapper->countByCriteria($criteria));
    }
}

// --- Test entity ---

class TestContact extends Base
{
}

// --- Test mapper using BaseMapper directly ---

class TestContactMapper extends BaseMapper
{
    protected $_table = 'contacts';
    protected $_classname = TestContact::class;
    protected $_setTimestamps = true;
    protected $_lazyFields = ['bio'];

    protected $_relationships = [
        'company' => [
            'type' => Constants::MANY_TO_ONE,
            'mapper' => 'CompanyMapper',
            'foreignKey' => 'company_id',
        ],
        'tags' => [
            'type' => Constants::MANY_TO_MANY,
            'mapper' => 'TagMapper',
            'through' => 'contact_tags',
        ],
    ];

    protected $_lazyRelationships = [
        'orders' => [
            'type' => Constants::ONE_TO_MANY,
            'mapper' => 'OrderMapper',
            'foreignKey' => 'contact_id',
        ],
        'notes' => [
            'type' => Constants::ONE_TO_MANY,
            'mapper' => 'NoteMapper',
            'foreignKey' => 'contact_id',
        ],
    ];
}

// --- Test mapper extending MapperSql (backward compat) ---

class TestMapperSqlChild extends MapperSql
{
    protected $_table = 'items';
    protected $_classname = TestContact::class;
    protected $_setTimestamps = false;
}

// --- Mapper with no timestamps ---

class TestNoTimestampMapper extends BaseMapper
{
    protected $_table = 'items';
    protected $_classname = TestContact::class;
    protected $_setTimestamps = false;
}

// --- Mapper with legacy adapter (no QuotingInterface) ---

class TestContactMapperLegacyAdapter extends BaseMapper
{
    protected $_table = 'contacts';
    protected $_classname = TestContact::class;
}

// --- Stub table definition ---

class StubTableDefinition
{
    private string $primaryKey;
    private array $columns;

    public function __construct(string $primaryKey, array $columns)
    {
        $this->primaryKey = $primaryKey;
        $this->columns = $columns;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getColumnNames(): array
    {
        return $this->columns;
    }

    public function getColumn(string $name): ?StubColumn
    {
        return in_array($name, $this->columns) ? new StubColumn() : null;
    }
}

class StubColumn
{
    public function typeCast(mixed $value): mixed
    {
        return $value;
    }
}

// --- Stub adapter that supports QuotingInterface + records calls ---

class MapperStubAdapter implements Adapter, QuotingInterface
{
    private array $calls = [];
    private array $overrides;
    private ?StubTableDefinition $tableDefinition = null;

    public function __construct(array $overrides = [])
    {
        $this->overrides = $overrides;
    }

    public function lastCall(string $method): ?array
    {
        foreach (array_reverse($this->calls) as $call) {
            if ($call['method'] === $method) {
                return $call;
            }
        }
        return null;
    }

    // --- Table definition (required by BaseMapper::__get) ---

    public function table($tableName)
    {
        if ($this->tableDefinition === null) {
            // Default: contacts table with typical columns
            $this->tableDefinition = new StubTableDefinition(
                'contact_id',
                ['contact_id', 'name', 'email', 'bio'],
            );
            if ($tableName === 'items') {
                $this->tableDefinition = new StubTableDefinition(
                    'item_id',
                    ['item_id', 'title'],
                );
            }
        }
        return $this->tableDefinition;
    }

    // --- QuotingInterface ---

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

    public function addLock(&$sql, array $options = [])
    {
    }

    // --- Query methods ---

    public function selectAll($sql, $arg1 = null, $arg2 = null)
    {
        $this->calls[] = ['method' => 'selectAll', 'sql' => $sql, 'params' => $arg1 ?? []];
        return $this->overrides['selectAll'] ?? [];
    }

    public function selectOne($sql, $arg1 = null, $arg2 = null)
    {
        $this->calls[] = ['method' => 'selectOne', 'sql' => $sql, 'params' => $arg1 ?? []];
        return $this->overrides['selectOne'] ?? null;
    }

    public function insertBlob($table, $fields, $pk = null, $idValue = null)
    {
        $this->calls[] = ['method' => 'insertBlob', 'table' => $table, 'fields' => $fields];
        return 1;
    }

    public function update($sql, $arg1 = null, $arg2 = null)
    {
        $this->calls[] = ['method' => 'update', 'sql' => $sql, 'params' => $arg1 ?? []];
        return 1;
    }

    public function delete($sql, $arg1 = null, $arg2 = null)
    {
        $this->calls[] = ['method' => 'delete', 'sql' => $sql, 'params' => $arg1 ?? []];
        return 1;
    }

    // --- Unused interface methods ---

    public function adapterName() { return 'stub'; }
    public function supportsMigrations() { return false; }
    public function supportsCountDistinct() { return true; }
    public function prefetchPrimaryKey($tableName = null) { return false; }
    public function connect() {}
    public function isActive() { return true; }
    public function reconnect() {}
    public function disconnect() {}
    public function rawConnection() { return null; }
    public function quoteString($string) { return "'" . addslashes($string) . "'"; }
    public function select($sql, $arg1 = null, $arg2 = null) { return null; }
    public function selectValue($sql, $arg1 = null, $arg2 = null) { return null; }
    public function selectValues($sql, $arg1 = null, $arg2 = null) { return []; }
    public function selectAssoc($sql, $arg1 = null, $arg2 = null) { return []; }
    public function execute($sql, $arg1 = null, $arg2 = null) { return null; }
    public function insert($sql, $arg1 = null, $arg2 = null, $pk = null, $idValue = null, $sequenceName = null) { return 1; }
    public function updateBlob($table, $fields, $where = '') {}
    public function transactionStarted() { return false; }
    public function beginDbTransaction() {}
    public function commitDbTransaction() {}
    public function rollbackDbTransaction() {}
    public function getLastQuery(): string { return ''; }
    public function cacheWrite(string $key, string $value) {}
    public function cacheRead($key) { return false; }
}
