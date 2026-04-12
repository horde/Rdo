# Horde\Rdo Usage Guide

This guide covers the new-style Rdo API: persistence-agnostic entities, typed
schemas, abstract criteria, and the repository pattern. These replace the
classic `Horde_Rdo_Base` / `Horde_Rdo_Mapper` / `Horde_Rdo_Query` active-record
classes for new code.

All classes live in the `Horde\Rdo` namespace (PSR-4, under `src/`).

---

## Table of Contents

1. [Entities](#entities)
2. [TypeSchema](#typeschema)
   - [Fluent Builder](#fluent-builder)
   - [PHP Attributes](#php-attributes)
3. [Criteria](#criteria)
   - [Field Conditions](#field-conditions)
   - [Combining Criteria](#combining-criteria)
   - [Negation](#negation)
   - [Relationship Existence](#relationship-existence)
   - [Raw Expressions](#raw-expressions)
   - [CriteriaBuilder (Ordering, Pagination, Eager Loading)](#criteriabuilder)
4. [Repository](#repository)
   - [Finding Entities](#finding-entities)
   - [Counting and Existence](#counting-and-existence)
   - [Saving](#saving)
   - [Deleting](#deleting)
   - [Raw Queries](#raw-queries)
5. [Hydrator](#hydrator)
6. [TypeRegistry](#typeregistry)
7. [Wiring It Together](#wiring-it-together)
8. [Field Types and Casting](#field-types-and-casting)
9. [Relationship Types](#relationship-types)
10. [Migration from Classic Rdo](#migration-from-classic-rdo)

---

## Entities

An entity is a plain PHP class. It has no base class, no interface to
implement, no magic methods. The schema describes how it maps to storage.

```php
class Contact
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public ?string $email = null,
        public ?string $phone = null,
    ) {}
}
```

Constructor promotion, readonly properties, and nullable types all work. The
hydrator fills constructor parameters first, then sets any remaining public
properties directly.

---

## TypeSchema

A `TypeSchema` describes an entity's fields, relationships, and table mapping.
There are two equivalent ways to build one: the fluent builder API and PHP
attributes.

### Fluent Builder

```php
use Horde\Rdo\FieldType;
use Horde\Rdo\TypeSchema;

$schema = (new TypeSchema(Contact::class, 'contacts'))
    ->id('id', FieldType::INT)
    ->field('name', FieldType::STRING)
    ->field('email', FieldType::STRING, nullable: true)
    ->field('phone', FieldType::STRING, nullable: true, column: 'phone_number')
    ->field('bio', FieldType::TEXT, lazy: true)
    ->hasMany('orders', Order::class, 'contact_id')
    ->belongsTo('company', Company::class, 'company_id')
    ->manyToMany('groups', Group::class, 'contact_groups', 'contact_id')
    ->timestamps();
```

**`id()`** declares the primary key. Defaults to `'id'` with `FieldType::INT`.
Pass a third argument for a custom column name.

**`field()`** declares a regular column. Named parameters control behavior:

| Parameter  | Default | Effect |
|------------|---------|--------|
| `nullable` | `false` | Allows null values |
| `lazy`     | `false` | Excluded from default SELECT (loaded on demand) |
| `column`   | `null`  | Backend column name if different from the field name |

If `table` is omitted from the constructor, it defaults to the lowercased short
class name (e.g., `App\Entity\Contact` becomes `contact`).

**`timestamps()`** enables automatic `created_at` / `updated_at` handling on
save.

### PHP Attributes

The same schema can be expressed with PHP 8 attributes on the entity class
itself:

```php
use Horde\Rdo\Attribute\BelongsTo;
use Horde\Rdo\Attribute\Column;
use Horde\Rdo\Attribute\HasMany;
use Horde\Rdo\Attribute\Id;
use Horde\Rdo\Attribute\ManyToMany;
use Horde\Rdo\Attribute\Table;
use Horde\Rdo\FieldType;

#[Table(name: 'contacts', timestamps: true)]
class Contact
{
    #[Id(type: FieldType::INT)]
    public int $id;

    #[Column]
    public string $name;

    #[Column(nullable: true)]
    public ?string $email = null;

    #[Column(name: 'phone_number', nullable: true)]
    public ?string $phone = null;

    #[Column(type: FieldType::TEXT, lazy: true)]
    public string $bio;

    #[BelongsTo(target: Company::class, foreignKey: 'company_id')]
    public ?Company $company = null;

    #[HasMany(target: Order::class, foreignKey: 'contact_id')]
    public iterable $orders;

    #[ManyToMany(target: Group::class, through: 'contact_groups')]
    public iterable $groups;
}
```

Read the schema with `AttributeSchemaReader`:

```php
use Horde\Rdo\AttributeSchemaReader;

$reader = new AttributeSchemaReader();
$schema = $reader->read(Contact::class);
```

When `#[Column]` or `#[Id]` omits the `type`, the reader infers it from the
PHP type declaration: `int` maps to `INT`, `float` to `FLOAT`, `bool` to
`BOOL`, `array` to `JSON`, and `DateTimeImmutable`/`DateTime`/`DateTimeInterface`
to `DATETIME`. Everything else defaults to `STRING`.

#### Available Attributes

| Attribute      | Target   | Parameters |
|----------------|----------|------------|
| `#[Table]`     | Class    | `name`, `timestamps` (default `false`) |
| `#[Id]`        | Property | `type` (auto-detected), `column` |
| `#[Column]`    | Property | `type` (auto-detected), `name` (column), `nullable`, `lazy` |
| `#[HasOne]`    | Property | `target`, `foreignKey`, `lazy` (default `true`) |
| `#[HasMany]`   | Property | `target`, `foreignKey`, `lazy` (default `true`) |
| `#[BelongsTo]` | Property | `target`, `foreignKey`, `lazy` (default `true`) |
| `#[ManyToMany]`| Property | `target`, `through`, `foreignKey`, `lazy` (default `true`) |

---

## Criteria

Criteria are the query language. A `Criterion` is either a leaf (single
condition) or a composite (logical combination). The tree IS the query --
backend translation happens separately through the Visitor pattern.

### Field Conditions

The `Field` class provides named constructors for every comparison:

```php
use Horde\Rdo\Field;

Field::equals('status', 'active');
Field::notEquals('role', 'guest');
Field::greaterThan('age', 18);
Field::lessThan('price', 100);
Field::greaterOrEqual('score', 80);
Field::lessOrEqual('priority', 3);
Field::in('status', ['active', 'pending']);
Field::notIn('role', ['banned', 'suspended']);
Field::isNull('deleted_at');
Field::isNotNull('email');
Field::contains('name', 'smith');        // LIKE '%smith%'
Field::startsWith('name', 'A');          // LIKE 'A%'
Field::between('created_at', '2026-01-01', '2026-12-31');
Field::search('bio', 'horde groupware'); // Full-text search intent
```

Each returns an immutable `FieldCriterion`. Field names refer to the entity's
logical field names as defined in the TypeSchema, not raw column names.

### Combining Criteria

Use `All` (AND) and `Any` (OR) to compose:

```php
use Horde\Rdo\All;
use Horde\Rdo\Any;
use Horde\Rdo\Field;

// Active users over 18
$criteria = All::of(
    Field::equals('status', 'active'),
    Field::greaterThan('age', 18),
);

// Admin or editor
$criteria = Any::of(
    Field::equals('role', 'admin'),
    Field::equals('role', 'editor'),
);
```

Composites nest naturally:

```php
// Active users who are either admin or have verified email
$criteria = All::of(
    Field::equals('status', 'active'),
    Any::of(
        Field::equals('role', 'admin'),
        Field::isNotNull('verified_at'),
    ),
);
```

### Negation

```php
use Horde\Rdo\Not;

// Not deleted
Not::of(Field::isNotNull('deleted_at'));

// Not in the banned list AND not a guest
All::of(
    Not::of(Field::in('id', $bannedIds)),
    Not::of(Field::equals('role', 'guest')),
);
```

### Relationship Existence

Query entities based on whether a relationship exists, optionally with
conditions on the related entity:

```php
use Horde\Rdo\Has;

// Contacts who have at least one order
Has::relation('orders');

// Contacts who have orders over $100
Has::relation('orders', Field::greaterThan('amount', 100));
```

The relationship name must match a relation defined in the TypeSchema.

### Raw Expressions

Escape hatch for backend-specific queries that can't be expressed through
the abstract Criterion API:

```php
use Horde\Rdo\Raw;

// PostGIS spatial query
Raw::sql('ST_DWithin(location, ST_MakePoint(?, ?), ?)', [$lng, $lat, $radius]);

// Database-specific function
Raw::sql('MATCH(bio) AGAINST(? IN BOOLEAN MODE)', [$searchTerms]);
```

Raw criteria are passed directly to the backend. They tie your query to a
specific backend -- use sparingly.

### CriteriaBuilder

Wrap any `Criterion` with ordering, pagination, and eager-loading hints:

```php
use Horde\Rdo\CriteriaBuilder;
use Horde\Rdo\Direction;
use Horde\Rdo\Field;

$query = CriteriaBuilder::from(Field::equals('status', 'active'))
    ->orderBy('name')
    ->orderBy('created_at', Direction::DESC)
    ->limit(20)
    ->offset(40)
    ->with('company', 'groups');
```

`CriteriaBuilder` is immutable -- each method returns a new instance. Pass
either a bare `Criterion` or a `CriteriaBuilder` to repository methods; both
are accepted.

---

## Repository

`Repository` is the consumer-facing interface for entity persistence:

```php
use Horde\Rdo\Repository;

interface Repository
{
    public function find(Criterion|CriteriaBuilder $criteria): iterable;
    public function findOne(mixed $id): ?object;
    public function count(Criterion|CriteriaBuilder $criteria): int;
    public function exists(mixed $id): bool;
    public function save(object $entity): void;
    public function delete(object $entity): void;
    public function findRaw(mixed $backendQuery, array $params = []): iterable;
}
```

`SqlRepository` is the SQL-backed implementation that connects criteria to
the Horde DBAL query builder.

### Finding Entities

```php
// Find by criteria
$contacts = $repo->find(Field::equals('status', 'active'));

// With ordering and pagination
$contacts = $repo->find(
    CriteriaBuilder::from(Field::equals('status', 'active'))
        ->orderBy('name')
        ->limit(25),
);

// Find one by primary key
$contact = $repo->findOne(42);

// Returns null if not found
$contact = $repo->findOne(999); // null
```

### Counting and Existence

```php
$count = $repo->count(Field::equals('status', 'active'));

$exists = $repo->exists(42); // bool
```

### Saving

`save()` determines insert vs. update automatically by checking whether the
primary key value already exists in the backend:

```php
// Insert (new entity, no existing PK in DB)
$contact = new Contact(0, 'Alice', 'alice@example.com');
$repo->save($contact);

// Update (existing PK found in DB)
$contact = $repo->findOne(42);
$contact->email = 'new@example.com';
$repo->save($contact);
```

When timestamps are enabled, `save()` sets `updated_at` on every save and
`created_at` on inserts.

### Deleting

```php
$contact = $repo->findOne(42);
$repo->delete($contact);
```

Requires a primary key defined in the schema.

### Raw Queries

Escape hatch for queries that bypass the Criteria layer entirely:

```php
$results = $repo->findRaw(
    'SELECT * FROM contacts WHERE name LIKE ? ORDER BY id',
    ['%smith%'],
);
```

Results are still hydrated through the normal Hydrator.

---

## Hydrator

A `Hydrator` converts between raw data arrays (database rows) and entity
objects. Two implementations are provided:

**`DefaultHydrator`** -- For typed entity classes. Uses reflection to fill
constructor parameters and set properties. Handles type casting between
database and PHP types based on the FieldType in the schema.

```php
use Horde\Rdo\DefaultHydrator;

$hydrator = new DefaultHydrator();

// Row from database -> entity object
$contact = $hydrator->hydrate(
    ['id' => '42', 'name' => 'Alice', 'email' => 'alice@example.com'],
    $schema,
);
// $contact->id === 42 (cast from string to int via FieldType::INT)

// Entity object -> data array for persistence
$data = $hydrator->extract($contact, $schema);
// ['id' => 42, 'name' => 'Alice', 'email' => 'alice@example.com']
```

**`FieldBagHydrator`** -- For dynamic entities that use `ArrayAccess` or
public property access. No type casting. Intended as a migration path for
legacy Rdo entities.

```php
use Horde\Rdo\FieldBagHydrator;

$hydrator = new FieldBagHydrator();
```

When the TypeSchema defines a column-to-field name mapping, both hydrators
translate between backend column names and entity field names automatically.

---

## TypeRegistry

The `TypeRegistry` is the central coordination point that maps entity classes
to their schemas and repositories:

```php
use Horde\Rdo\TypeRegistry;

$registry = new TypeRegistry();

$registry->register($contactSchema, $contactRepo);
$registry->register($orderSchema, $orderRepo);

// Later, look up by entity class
$schema = $registry->getSchema(Contact::class);
$repo   = $registry->getRepository(Contact::class);

$registry->has(Contact::class); // true
$registry->has(Unknown::class); // false
```

Populate it at application boot (typically via the dependency injector).

---

## Wiring It Together

A complete setup using the fluent schema builder:

```php
use Horde\Rdo\DefaultHydrator;
use Horde\Rdo\FieldType;
use Horde\Rdo\SqlRepository;
use Horde\Rdo\TypeRegistry;
use Horde\Rdo\TypeSchema;

// 1. Define the schema
$schema = (new TypeSchema(Contact::class, 'contacts'))
    ->id('id', FieldType::INT)
    ->field('name', FieldType::STRING)
    ->field('email', FieldType::STRING, nullable: true)
    ->hasMany('orders', Order::class, 'contact_id')
    ->timestamps();

// 2. Create hydrator and repository
$hydrator = new DefaultHydrator();
$repo = new SqlRepository($dbAdapter, $schema, $hydrator);

// 3. Register (optional, for central lookup)
$registry = new TypeRegistry();
$registry->register($schema, $repo);

// 4. Use it
$contacts = $repo->find(
    CriteriaBuilder::from(Field::equals('status', 'active'))
        ->orderBy('name')
        ->limit(10),
);

foreach ($contacts as $contact) {
    echo $contact->name . "\n";
}
```

Or using PHP attributes:

```php
use Horde\Rdo\AttributeSchemaReader;
use Horde\Rdo\DefaultHydrator;
use Horde\Rdo\SqlRepository;

// 1. Read schema from attributes on the entity class
$reader = new AttributeSchemaReader();
$schema = $reader->read(Contact::class);

// 2. Create hydrator and repository
$hydrator = new DefaultHydrator();
$repo = new SqlRepository($dbAdapter, $schema, $hydrator);
```

---

## Field Types and Casting

The `DefaultHydrator` casts values between backend (typically string) and PHP
types based on the `FieldType` in the schema:

| FieldType      | PHP Type             | Backend Format                  |
|----------------|----------------------|---------------------------------|
| `STRING`       | `string`             | Passed through                  |
| `INT`          | `int`                | Cast via `(int)`                |
| `FLOAT`        | `float`              | Cast via `(float)`              |
| `BOOL`         | `bool`               | `1` / `0` in backend            |
| `DATETIME`     | `DateTimeImmutable`  | `Y-m-d H:i:s` string           |
| `TEXT`          | `string`             | Passed through (same as STRING) |
| `BINARY`       | `string`             | Passed through                  |
| `JSON`         | `array`              | JSON string in backend          |
| `SERIALIZED`   | `mixed`              | PHP `serialize()` string        |

`TEXT` and `BINARY` behave like `STRING` for casting but carry semantic meaning
for schema introspection and lazy-loading decisions.

---

## Relationship Types

| Type           | Schema Method  | Attribute       | FK Location     |
|----------------|----------------|-----------------|-----------------|
| Has One        | `hasOne()`     | `#[HasOne]`     | On target table |
| Has Many       | `hasMany()`    | `#[HasMany]`    | On target table |
| Belongs To     | `belongsTo()`  | `#[BelongsTo]`  | On source table |
| Many to Many   | `manyToMany()` | `#[ManyToMany]` | Pivot table     |

All relationships default to `lazy: true`. Set `lazy: false` for eager
loading in the schema definition.

When using `Has::relation()` in criteria, the SQL visitor generates
`WHERE EXISTS` subqueries based on the relationship metadata in the
TypeSchema.

---

## Migration from Classic Rdo

The new classes coexist with the classic `Horde_Rdo_Base` / `Horde_Rdo_Mapper`
/ `Horde_Rdo_Query` classes under `lib/`. Existing code continues to work
unchanged. Migrate either to the MapperSql drop-in replacement or to the all-new SqlRepository.
The former is straight forward and mechanical.
The latter is a more involved upgrade which unlocks more control.
See migration-example.php for comparison.

### Concept Mapping

Before looking at code: This is how classic Rdo concepts translate:

| Classic Rdo                | New-style equivalent          |
|----------------------------|-------------------------------|
| `Horde_Rdo_Base` subclass  | Plain PHP class (entity)      |
| `Horde_Rdo_Mapper` subclass| `SqlRepository`               |
| `Horde_Rdo_Query`          | `Criterion` / `CriteriaBuilder` |
| `$_table`                  | `TypeSchema::getTable()`      |
| `$_relationships`          | `hasOne()`, `hasMany()`, etc. |
| `$_lazyRelationships`      | Relationships with `lazy: true` |
| `$_lazyFields`             | Fields with `lazy: true`      |
| `$_setTimestamps`          | `timestamps()`                |
| `Horde_Rdo_Factory`        | `TypeRegistry`                |
| `addTest($field, $op, $v)` | `Field::equals()`, etc.       |
| `sortBy('name ASC')`       | `->orderBy('name')`           |
| `$mapper->find($query)`    | `$repo->find($criteria)`      |
| `$mapper->findOne($id)`    | `$repo->findOne($id)`         |
| `$mapper->count($query)`   | `$repo->count($criteria)`     |

### Step 1: Verbatim Conversion

The first step is a mechanical translation. Take what the mapper and entity
already do and express it with the new classes, without changing behavior.

**Classic entity** (extends `Horde_Rdo_Base`, stores fields in `$_fields`
array, accessed via `__get` / `__set`):

```php
class Sesha_Entity_Stock extends Horde_Rdo_Base
{
    // All fields come from the database via __get
}
```

**Classic mapper** (extends `Horde_Rdo_Mapper`, declares table, relationships,
and custom logic):

```php
class Sesha_Entity_StockMapper extends Horde_Rdo_Mapper
{
    protected $_table = 'sesha_inventory';

    protected $_lazyRelationships = [
        'categories' => [
            'type' => Horde_Rdo::MANY_TO_MANY,
            'mapper' => 'Sesha_Entity_CategoryMapper',
            'through' => 'sesha_inventory_categories',
        ],
        'values' => [
            'type' => Horde_Rdo::ONE_TO_MANY,
            'mapper' => 'Sesha_Entity_ValueMapper',
            'foreignKey' => 'stock_id',
        ],
    ];
}
```

**Verbatim new-style equivalent** -- the entity becomes a plain class and the
mapper becomes a TypeSchema + SqlRepository pair. Use `FieldBagHydrator` to
preserve the dynamic property behavior during transition:

```php
// Entity -- plain class, no base class
class Stock
{
    public int $stock_id;
    public string $stock_name = '';
    public string $note = '';
}
```

```php
// Schema -- transcribes what the mapper declared
$schema = (new TypeSchema(Stock::class, 'sesha_inventory'))
    ->id('stock_id', FieldType::INT)
    ->field('stock_name', FieldType::STRING)
    ->field('note', FieldType::STRING, nullable: true)
    ->hasMany('values', Value::class, 'stock_id')
    ->manyToMany('categories', Category::class, 'sesha_inventory_categories');
```

```php
// Repository -- replaces the mapper class
$hydrator = new FieldBagHydrator();  // or DefaultHydrator for typed entities
$repo = new SqlRepository($adapter, $schema, $hydrator);
```

At this point calling code changes are minimal:

```php
// Before
$mapper = new Sesha_Entity_StockMapper($adapter);
$stock = $mapper->findOne($stockId);

// After
$stock = $repo->findOne($stockId);
```

```php
// Before
$query = new Horde_Rdo_Query($mapper);
$query->addTest('stock_name', 'LIKE', '%server%');
$results = $mapper->find($query);

// After
$results = $repo->find(Field::contains('stock_name', 'server'));
```

### Step 2: Adopt Typed Entities

Once the verbatim conversion is working you can optionally move to fully
typed entities with constructor promotion and switch to `DefaultHydrator`
for automatic type casting:

```php
class Stock
{
    public function __construct(
        public readonly int $stock_id,
        public string $stock_name,
        public ?string $note = null,
    ) {}
}
```

```php
$hydrator = new DefaultHydrator();
$repo = new SqlRepository($adapter, $schema, $hydrator);

$stock = $repo->findOne(42);
// $stock->stock_id is int, not string -- DefaultHydrator casts it
```

### Step 3: Explore New Capabilities (When Ready)

The criteria system, relationship queries and attribute-based schemas are
available when you want them. There is no requirement to adopt everything
at once.

**Composable criteria** replace chains of `addTest()`:

```php
// Before
$query = new Horde_Rdo_Query($mapper);
$query->addTest('stock_name', 'LIKE', '%server%');
$query->addTest('note', '!=', '');
$query->sortBy('stock_name ASC');
$query->limit(25, 50);
$results = $mapper->find($query);

// After
$results = $repo->find(
    CriteriaBuilder::from(All::of(
        Field::contains('stock_name', 'server'),
        Field::notEquals('note', ''),
    ))
        ->orderBy('stock_name')
        ->limit(25)
        ->offset(50),
);
```

**Relationship existence queries** replace manual JOINs or subqueries:

```php
// Stocks that have at least one value assigned
$results = $repo->find(Has::relation('values'));

// Stocks in a specific category (via the pivot table)
$results = $repo->find(
    Has::relation('categories', Field::equals('category_id', 5)),
);
```

**Attribute-based schemas** move the mapping onto the entity class itself,
eliminating the separate schema setup:

```php
use Horde\Rdo\Attribute\Column;
use Horde\Rdo\Attribute\HasMany;
use Horde\Rdo\Attribute\Id;
use Horde\Rdo\Attribute\ManyToMany;
use Horde\Rdo\Attribute\Table;
use Horde\Rdo\FieldType;

#[Table(name: 'sesha_inventory')]
class Stock
{
    #[Id(type: FieldType::INT)]
    public int $stock_id;

    #[Column]
    public string $stock_name;

    #[Column(nullable: true)]
    public ?string $note = null;

    #[HasMany(target: Value::class, foreignKey: 'stock_id')]
    public iterable $values;

    #[ManyToMany(target: Category::class, through: 'sesha_inventory_categories')]
    public iterable $categories;
}

// Schema is now derived from the class:
$reader = new AttributeSchemaReader();
$schema = $reader->read(Stock::class);
```

### Custom Mapper Logic

Classic mappers sometimes override `delete()` or `create()` to handle
cascading operations. In the new style, this logic lives in a service class
or a subclass of `SqlRepository`:

```php
// Classic: custom cascade delete in the mapper
class StockMapper extends Horde_Rdo_Mapper
{
    public function delete($object)
    {
        foreach ($object->values as $value) {
            $value->delete();
        }
        $object->removeRelation('categories');
        return parent::delete($object);
    }
}

// New-style: service class or repository subclass
class StockRepository extends SqlRepository
{
    public function delete(object $entity): void
    {
        // Clean up related values first
        $valueRepo = $this->registry->getRepository(Value::class);
        $values = $valueRepo->find(Field::equals('stock_id', $entity->stock_id));
        foreach ($values as $value) {
            $valueRepo->delete($value);
        }

        parent::delete($entity);
    }
}
```

### Pace of Migration

The classic and new-style classes coexist indefinitely. You can migrate one
mapper at a time, or even have classic and new-style code access the same
tables simultaneously. The path is:

1. **Verbatim conversion** -- plain entity + TypeSchema + SqlRepository,
   `FieldBagHydrator`, minimal calling-code changes. This is a mechanical
   step that can be done for all mappers in a component at once.
2. **Typed entities** -- add type declarations, switch to `DefaultHydrator`.
   Do this per-entity as you touch the code.
3. **New capabilities** -- composable criteria, relationship queries,
   attribute schemas. Adopt these as you write new features or refactor
   existing query logic.
