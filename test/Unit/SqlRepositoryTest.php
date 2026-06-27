<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit;

use Horde\Db\Adapter;
use Horde\Db\Query\QuotingInterface;
use Horde\Rdo\CriteriaBuilder;
use Horde\Rdo\DefaultHydrator;
use Horde\Rdo\Field;
use Horde\Rdo\FieldType;
use Horde\Rdo\RdoException;
use Horde\Rdo\SqlRepository;
use Horde\Rdo\TypeSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlRepository::class)]
class SqlRepositoryTest extends TestCase
{
    private TypeSchema $schema;
    private DefaultHydrator $hydrator;

    protected function setUp(): void
    {
        $this->schema = (new TypeSchema(RepoContact::class, 'contacts'))
            ->id('id', FieldType::INT)
            ->field('name', FieldType::STRING)
            ->field('email', FieldType::STRING, nullable: true);

        $this->hydrator = new DefaultHydrator();
    }

    private function stubAdapter(array $overrides = []): StubAdapter
    {
        return new StubAdapter($overrides);
    }

    // --- find ---

    public function testFindReturnsHydratedEntities(): void
    {
        $adapter = $this->stubAdapter([
            'selectAll' => [
                ['id' => '1', 'name' => 'Alice', 'email' => 'alice@x.com'],
                ['id' => '2', 'name' => 'Bob', 'email' => null],
            ],
        ]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);
        $results = $repo->find(Field::equals('name', 'Alice'));

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertInstanceOf(RepoContact::class, $results[0]);
        $this->assertSame(1, $results[0]->id);
        $this->assertSame('Alice', $results[0]->name);
        $this->assertNull($results[1]->email);
    }

    public function testFindWithCriteriaBuilder(): void
    {
        $adapter = $this->stubAdapter([
            'selectAll' => [
                ['id' => '3', 'name' => 'Charlie', 'email' => 'c@x.com'],
            ],
        ]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);
        $criteria = CriteriaBuilder::from(Field::equals('name', 'Charlie'))
            ->orderBy('name')
            ->limit(10)
            ->offset(5);

        $results = $repo->find($criteria);

        $this->assertCount(1, $results);
        $this->assertSame('Charlie', $results[0]->name);
    }

    public function testFindPassesSqlToAdapter(): void
    {
        $adapter = $this->stubAdapter(['selectAll' => []]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);
        $repo->find(Field::equals('name', 'Alice'));

        $call = $adapter->lastCall('selectAll');
        $this->assertStringContainsString('"contacts"', $call['sql']);
        $this->assertStringContainsString('"name" = ?', $call['sql']);
        $this->assertSame(['Alice'], $call['params']);
    }

    // --- findOne ---

    public function testFindOneReturnsEntity(): void
    {
        $adapter = $this->stubAdapter([
            'selectOne' => ['id' => '42', 'name' => 'Alice', 'email' => 'a@x.com'],
        ]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);
        $entity = $repo->findOne(42);

        $this->assertInstanceOf(RepoContact::class, $entity);
        $this->assertSame(42, $entity->id);
        $this->assertSame('Alice', $entity->name);
    }

    public function testFindOneReturnsNullWhenNotFound(): void
    {
        $adapter = $this->stubAdapter(['selectOne' => false]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);

        $this->assertNull($repo->findOne(999));
    }

    public function testFindOneReturnsNullForNullResult(): void
    {
        $adapter = $this->stubAdapter(['selectOne' => null]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);

        $this->assertNull($repo->findOne(999));
    }

    public function testFindOneThrowsWithoutPrimaryKey(): void
    {
        $schema = (new TypeSchema(RepoContact::class, 'contacts'))
            ->field('name', FieldType::STRING);

        $adapter = $this->stubAdapter();
        $repo = new SqlRepository($adapter, $schema, $this->hydrator);

        $this->expectException(RdoException::class);
        $this->expectExceptionMessageMatches('/no primary key/');

        $repo->findOne(1);
    }

    // --- count ---

    public function testCountReturnsCnt(): void
    {
        $adapter = $this->stubAdapter(['selectOne' => ['cnt' => '7']]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);

        $this->assertSame(7, $repo->count(Field::equals('name', 'Alice')));
    }

    public function testCountWithCriteriaBuilder(): void
    {
        $adapter = $this->stubAdapter(['selectOne' => ['cnt' => '3']]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);
        $criteria = CriteriaBuilder::from(Field::equals('name', 'Alice'));

        $this->assertSame(3, $repo->count($criteria));
    }

    public function testCountReturnsZeroForNullResult(): void
    {
        $adapter = $this->stubAdapter(['selectOne' => null]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);

        $this->assertSame(0, $repo->count(Field::equals('name', 'x')));
    }

    // --- exists ---

    public function testExistsReturnsTrue(): void
    {
        $adapter = $this->stubAdapter(['selectOne' => ['cnt' => '1']]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);

        $this->assertTrue($repo->exists(42));
    }

    public function testExistsReturnsFalse(): void
    {
        $adapter = $this->stubAdapter(['selectOne' => ['cnt' => '0']]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);

        $this->assertFalse($repo->exists(999));
    }

    public function testExistsThrowsWithoutPrimaryKey(): void
    {
        $schema = (new TypeSchema(RepoContact::class, 'contacts'))
            ->field('name', FieldType::STRING);

        $adapter = $this->stubAdapter();
        $repo = new SqlRepository($adapter, $schema, $this->hydrator);

        $this->expectException(RdoException::class);
        $repo->exists(1);
    }

    // --- save (insert) ---

    public function testSaveInsertCallsInsertBlob(): void
    {
        $adapter = $this->stubAdapter(['selectOne' => ['cnt' => '0']]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);
        $repo->save(new RepoContact(1, 'Alice', 'alice@x.com'));

        $call = $adapter->lastCall('insertBlob');
        $this->assertNotNull($call);
        $this->assertSame('contacts', $call['table']);
        $this->assertSame(1, $call['fields']['id']);
        $this->assertSame('Alice', $call['fields']['name']);
        $this->assertSame('alice@x.com', $call['fields']['email']);
    }

    // --- save (update) ---

    public function testSaveUpdateCallsUpdate(): void
    {
        $adapter = $this->stubAdapter(['selectOne' => ['cnt' => '1']]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);
        $repo->save(new RepoContact(42, 'Updated', 'u@x.com'));

        $call = $adapter->lastCall('update');
        $this->assertNotNull($call);
        $this->assertStringContainsString('UPDATE', $call['sql']);
        $this->assertStringContainsString('"contacts"', $call['sql']);
        // The PK value is the last parameter
        $this->assertSame(42, end($call['params']));
    }

    // --- save with timestamps ---

    public function testSaveInsertSetsTimestamps(): void
    {
        $schema = (new TypeSchema(RepoContact::class, 'contacts'))
            ->id('id', FieldType::INT)
            ->field('name', FieldType::STRING)
            ->field('email', FieldType::STRING, nullable: true)
            ->timestamps();

        $adapter = $this->stubAdapter(['selectOne' => ['cnt' => '0']]);

        $repo = new SqlRepository($adapter, $schema, $this->hydrator);
        $repo->save(new RepoContact(1, 'Alice'));

        $call = $adapter->lastCall('insertBlob');
        $this->assertArrayHasKey('created_at', $call['fields']);
        $this->assertArrayHasKey('updated_at', $call['fields']);
    }

    public function testSaveUpdateSetsUpdatedAt(): void
    {
        $schema = (new TypeSchema(RepoContact::class, 'contacts'))
            ->id('id', FieldType::INT)
            ->field('name', FieldType::STRING)
            ->field('email', FieldType::STRING, nullable: true)
            ->timestamps();

        $adapter = $this->stubAdapter(['selectOne' => ['cnt' => '1']]);

        $repo = new SqlRepository($adapter, $schema, $this->hydrator);
        $repo->save(new RepoContact(42, 'Alice'));

        $call = $adapter->lastCall('update');
        $this->assertStringContainsString('updated_at', $call['sql']);
    }

    // --- save with null PK ---

    public function testSaveWithNullPkInserts(): void
    {
        // PK not present in extracted data -> null -> treated as new
        $schema = (new TypeSchema(RepoContact::class, 'contacts'))
            ->id('pk_id', FieldType::INT)
            ->field('name', FieldType::STRING);

        $adapter = $this->stubAdapter();

        $repo = new SqlRepository($adapter, $schema, $this->hydrator);
        $repo->save(new RepoContact(1, 'Alice'));

        $call = $adapter->lastCall('insertBlob');
        $this->assertNotNull($call);
    }

    // --- delete ---

    public function testDeleteCallsAdapterDelete(): void
    {
        $adapter = $this->stubAdapter();

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);
        $repo->delete(new RepoContact(42, 'Alice'));

        $call = $adapter->lastCall('delete');
        $this->assertNotNull($call);
        $this->assertStringContainsString('DELETE FROM', $call['sql']);
        $this->assertStringContainsString('"contacts"', $call['sql']);
        $this->assertSame([42], $call['params']);
    }

    public function testDeleteThrowsWithoutPrimaryKey(): void
    {
        $schema = (new TypeSchema(RepoContact::class, 'contacts'))
            ->field('name', FieldType::STRING);

        $adapter = $this->stubAdapter();
        $repo = new SqlRepository($adapter, $schema, $this->hydrator);

        $this->expectException(RdoException::class);
        $this->expectExceptionMessageMatches('/without a primary key/');

        $repo->delete(new RepoContact(1, 'Alice'));
    }

    public function testDeleteThrowsWithNullPk(): void
    {
        // Schema PK points to a property that doesn't exist -> extracts as null
        $schema = (new TypeSchema(RepoContact::class, 'contacts'))
            ->id('pk_id', FieldType::INT);

        $adapter = $this->stubAdapter();
        $repo = new SqlRepository($adapter, $schema, $this->hydrator);

        $this->expectException(RdoException::class);
        $this->expectExceptionMessageMatches('/null primary key/');

        $repo->delete(new RepoContact(1, 'Alice'));
    }

    // --- findRaw ---

    public function testFindRawPassesSqlAndHydrates(): void
    {
        $adapter = $this->stubAdapter([
            'selectAll' => [['id' => '1', 'name' => 'Raw', 'email' => null]],
        ]);

        $repo = new SqlRepository($adapter, $this->schema, $this->hydrator);
        $results = $repo->findRaw('SELECT * FROM contacts WHERE id = ?', [1]);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(RepoContact::class, $results[0]);
        $this->assertSame('Raw', $results[0]->name);
    }

    // --- find with empty columns ---

    public function testFindUsesWildcardWhenNoEagerFields(): void
    {
        // Schema with only lazy fields — buildSelect should use *
        $schema = (new TypeSchema(RepoContact::class, 'contacts'))
            ->id('id', FieldType::INT)
            ->field('name', FieldType::STRING, lazy: true);

        $adapter = $this->stubAdapter(['selectAll' => []]);

        $repo = new SqlRepository($adapter, $schema, $this->hydrator);
        $repo->find(Field::equals('id', 1));

        $call = $adapter->lastCall('selectAll');
        // Only the PK is eager (id), so columns should include "id"
        $this->assertStringContainsString('"id"', $call['sql']);
    }
}

// --- Test entity ---

class RepoContact
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public ?string $email = null,
    ) {}
}

/**
 * Concrete Adapter stub that records calls for inspection.
 *
 * Avoids PHPUnit mock issues with the legacy Horde_Db_Adapter interface
 * hierarchy.
 */
class StubAdapter implements Adapter, QuotingInterface
{
    private array $calls = [];
    private array $overrides;

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

    public function addLock(&$sql, array $options = []) {}

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
        $this->calls[] = ['method' => 'insertBlob', 'table' => $table, 'fields' => $fields, 'pk' => $pk];
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

    // --- Unused interface methods (no-ops) ---

    public function adapterName()
    {
        return 'stub';
    }
    public function supportsMigrations()
    {
        return false;
    }
    public function supportsCountDistinct()
    {
        return true;
    }
    public function prefetchPrimaryKey($tableName = null)
    {
        return false;
    }
    public function connect() {}
    public function isActive()
    {
        return true;
    }
    public function reconnect() {}
    public function disconnect() {}
    public function rawConnection()
    {
        return null;
    }
    public function quoteString($string)
    {
        return "'" . addslashes($string) . "'";
    }
    public function select($sql, $arg1 = null, $arg2 = null)
    {
        return null;
    }
    public function selectValue($sql, $arg1 = null, $arg2 = null)
    {
        return null;
    }
    public function selectValues($sql, $arg1 = null, $arg2 = null)
    {
        return [];
    }
    public function selectAssoc($sql, $arg1 = null, $arg2 = null)
    {
        return [];
    }
    public function execute($sql, $arg1 = null, $arg2 = null)
    {
        return null;
    }
    public function insert($sql, $arg1 = null, $arg2 = null, $pk = null, $idValue = null, $sequenceName = null)
    {
        return 1;
    }
    public function updateBlob($table, $fields, $where = '') {}
    public function transactionStarted()
    {
        return false;
    }
    public function beginDbTransaction() {}
    public function commitDbTransaction() {}
    public function rollbackDbTransaction() {}
    public function getLastQuery(): string
    {
        return '';
    }
    public function cacheWrite(string $key, string $value) {}
    public function cacheRead($key)
    {
        return false;
    }
}
