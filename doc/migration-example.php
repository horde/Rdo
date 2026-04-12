#!/usr/bin/env php
<?php

/**
 * Horde\Rdo Migration Example
 *
 * Demonstrates three ways to work with the same database table using
 * Horde's Rdo ORM, showing the migration path from the legacy lib/
 * API through to the new Criterion-based model.
 *
 * All three approaches share a single Horde\Db\Adapter\Pdo\Sqlite
 * instance. The adapter implements both Horde_Db_Adapter (needed by
 * legacy Horde_Rdo_Mapper and src/ MapperSql) and QuotingInterface
 * (needed by the new Criterion query layer).
 *
 * Table: horde_users (from Horde_Auth)
 *   user_uid                VARCHAR(255) PRIMARY KEY
 *   user_pass               VARCHAR(255) NOT NULL
 *   user_soft_expiration_date  INTEGER
 *   user_hard_expiration_date  INTEGER
 *
 * Usage:
 *   cd ~/php/git/horde/Rdo
 *   php doc/migration-example.php
 */

declare(strict_types=1);

// Autoload from the component's own vendor directory.
require_once __DIR__ . '/../vendor/autoload.php';

use Horde\Db\Adapter\Pdo\Sqlite as SqliteAdapter;

// ─── Create shared adapter and seed the database ───────────────────────

$adapter = new SqliteAdapter(['dbname' => ':memory:']);

$adapter->execute('
    CREATE TABLE horde_users (
        user_uid                 VARCHAR(255) PRIMARY KEY,
        user_pass                VARCHAR(255) NOT NULL,
        user_soft_expiration_date INTEGER,
        user_hard_expiration_date INTEGER
    )
');

$adapter->execute("INSERT INTO horde_users VALUES ('alice', 'hash_alice', NULL, NULL)");
$adapter->execute("INSERT INTO horde_users VALUES ('bob',   'hash_bob',   1700000000, 1800000000)");
$adapter->execute("INSERT INTO horde_users VALUES ('carol', 'hash_carol', NULL, 1900000000)");

echo "=== Horde\\Rdo Migration Example ===\n";
echo "Adapter: " . get_class($adapter) . "\n";
echo "  implements Horde_Db_Adapter: " . ($adapter instanceof Horde_Db_Adapter ? 'yes' : 'no') . "\n";
echo "  implements QuotingInterface: " . ($adapter instanceof Horde\Db\Query\QuotingInterface ? 'yes' : 'no') . "\n";
echo "\n";

// ═══════════════════════════════════════════════════════════════════════
// APPROACH 1: Legacy lib/ — Horde_Rdo_Mapper + Horde_Rdo_Base
// ═══════════════════════════════════════════════════════════════════════
//
// This is the traditional Horde 5/6 API. Entity classes extend
// Horde_Rdo_Base, mapper classes extend Horde_Rdo_Mapper. Field access
// is dynamic (__get/__set), and queries use arrays or Horde_Rdo_Query.

echo "--- Approach 1: Legacy lib/ (Horde_Rdo_Mapper) ---\n\n";

// Entity: just extend Horde_Rdo_Base — nothing else needed.
class LegacyUser extends Horde_Rdo_Base
{
}

// Mapper: set $_table to match the database table.
// The class name "LegacyUserMapper" auto-maps to entity "LegacyUser".
class LegacyUserMapper extends Horde_Rdo_Mapper
{
    protected $_table = 'horde_users';
    protected $_classname = LegacyUser::class;
    protected $_setTimestamps = false;
}

$legacyMapper = new LegacyUserMapper($adapter);

// findOne by primary key
$user = $legacyMapper->findOne('alice');
echo "findOne('alice'): uid={$user->user_uid}, pass={$user->user_pass}\n";

// find() returns Horde_Rdo_List (lazy iterator)
$all = $legacyMapper->find();
echo "find() all users:\n";
foreach ($all as $u) {
    echo "  uid={$u->user_uid}";
    if ($u->user_hard_expiration_date) {
        echo ", hard_exp={$u->user_hard_expiration_date}";
    }
    echo "\n";
}

// find with array criteria
$query = new Horde_Rdo_Query($legacyMapper);
$query->addTest('user_uid', '=', 'bob');
$bob = $legacyMapper->findOne($query);
echo "findOne(query uid=bob): uid={$bob->user_uid}\n";

echo "\n";

// ═══════════════════════════════════════════════════════════════════════
// APPROACH 2: src/ MapperSql — Drop-in replacement
// ═══════════════════════════════════════════════════════════════════════
//
// MapperSql (Horde\Rdo\MapperSql) is a 1:1 replacement for
// Horde_Rdo_Mapper. The constructor, protected properties, and all
// public methods have the same signatures and behavior. Entities extend
// Horde\Rdo\Base instead of Horde_Rdo_Base.
//
// Migration: change two base classes, keep everything else identical.

echo "--- Approach 2: src/ MapperSql (drop-in replacement) ---\n\n";

use Horde\Rdo\Base;
use Horde\Rdo\MapperSql;
use Horde\Rdo\Constants;

// Entity: extend Horde\Rdo\Base instead of Horde_Rdo_Base.
class ModernUser extends Base
{
}

// Mapper: extend MapperSql instead of Horde_Rdo_Mapper.
// Same protected properties, same patterns.
class ModernUserMapper extends MapperSql
{
    protected $_table = 'horde_users';
    protected $_classname = ModernUser::class;
    protected $_setTimestamps = false;
}

$modernMapper = new ModernUserMapper($adapter);

// findOne by primary key — identical API
$user = $modernMapper->findOne('alice');
echo "findOne('alice'): uid={$user->user_uid}, pass={$user->user_pass}\n";

// find() — identical API, returns DefaultList
$all = $modernMapper->find();
echo "find() all users:\n";
foreach ($all as $u) {
    echo "  uid={$u->user_uid}";
    if ($u->user_hard_expiration_date) {
        echo ", hard_exp={$u->user_hard_expiration_date}";
    }
    echo "\n";
}

// ── Bonus: MapperSql also exposes the new Criterion API ──
// Because the adapter implements QuotingInterface, you can optionally
// use findByCriteria() for type-safe, composable queries.

use Horde\Rdo\Field;
use Horde\Rdo\CriteriaBuilder;

$results = $modernMapper->findByCriteria(
    Field::equals('user_uid', 'bob')
);
echo "findByCriteria(uid=bob): uid={$results[0]->user_uid}\n";

// Composed criteria with builder
$criteria = CriteriaBuilder::from(
    Field::isNotNull('user_hard_expiration_date')
)->limit(10);

$expiring = $modernMapper->findByCriteria($criteria);
echo "findByCriteria(hard_exp IS NOT NULL): " . count($expiring) . " user(s)\n";

$count = $modernMapper->countByCriteria(
    Field::isNotNull('user_hard_expiration_date')
);
echo "countByCriteria(hard_exp IS NOT NULL): {$count}\n";

// TypeSchema is available for introspection
$schema = $modernMapper->getTypeSchema();
echo "TypeSchema table: {$schema->getTable()}, PK: {$schema->getPrimaryKey()->name}\n";

echo "\n";

// ═══════════════════════════════════════════════════════════════════════
// APPROACH 3: New Model — TypeSchema + SqlRepository
// ═══════════════════════════════════════════════════════════════════════
//
// The new model is fully decoupled from the Mapper pattern. You define
// a plain PHP entity class (no base class required), describe it with a
// TypeSchema, and use SqlRepository for persistence. Queries use the
// Criterion object model throughout.

echo "--- Approach 3: New Model (TypeSchema + SqlRepository) ---\n\n";

use Horde\Rdo\TypeSchema;
use Horde\Rdo\FieldType;
use Horde\Rdo\SqlRepository;
use Horde\Rdo\DefaultHydrator;

// Entity: plain PHP class — no base class, no magic, just typed properties.
class AuthUser
{
    public function __construct(
        public string $user_uid = '',
        public string $user_pass = '',
        public ?int $user_soft_expiration_date = null,
        public ?int $user_hard_expiration_date = null,
    ) {}
}

// Schema: declarative, fluent definition.
$userSchema = (new TypeSchema(AuthUser::class, 'horde_users'))
    ->id('user_uid', FieldType::STRING)
    ->field('user_pass', FieldType::STRING)
    ->field('user_soft_expiration_date', FieldType::INT, nullable: true)
    ->field('user_hard_expiration_date', FieldType::INT, nullable: true);

// Repository: takes the adapter, schema, and a hydrator.
$hydrator = new DefaultHydrator();
$repo = new SqlRepository($adapter, $userSchema, $hydrator);

// findOne by primary key
$user = $repo->findOne('alice');
echo "findOne('alice'): uid={$user->user_uid}, pass={$user->user_pass}\n";

// find with Criterion
$all = $repo->find(Field::greaterOrEqual('user_uid', ''));
echo "find(all): " . count($all) . " user(s)\n";
foreach ($all as $u) {
    echo "  uid={$u->user_uid}";
    if ($u->user_hard_expiration_date) {
        echo ", hard_exp={$u->user_hard_expiration_date}";
    }
    echo "\n";
}

// Composed query
$criteria = CriteriaBuilder::from(
    Field::isNotNull('user_hard_expiration_date')
)->orderBy('user_uid')
 ->limit(10);

$expiring = $repo->find($criteria);
echo "find(hard_exp IS NOT NULL): " . count($expiring) . " user(s)\n";

// count
$count = $repo->count(Field::isNotNull('user_hard_expiration_date'));
echo "count(hard_exp IS NOT NULL): {$count}\n";

// exists
echo "exists('alice'): " . ($repo->exists('alice') ? 'yes' : 'no') . "\n";
echo "exists('nobody'): " . ($repo->exists('nobody') ? 'yes' : 'no') . "\n";

// save (insert)
$repo->save(new AuthUser('dave', 'hash_dave'));
echo "save(dave): inserted\n";
echo "exists('dave'): " . ($repo->exists('dave') ? 'yes' : 'no') . "\n";

// delete
$repo->delete(new AuthUser('dave'));
echo "delete(dave): removed\n";
echo "exists('dave'): " . ($repo->exists('dave') ? 'yes' : 'no') . "\n";

echo "\n=== Done ===\n";
