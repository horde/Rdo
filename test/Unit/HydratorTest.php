<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit;

use DateTimeImmutable;
use Horde\Rdo\DefaultHydrator;
use Horde\Rdo\FieldBagHydrator;
use Horde\Rdo\FieldType;
use Horde\Rdo\Test\Unit\Fixtures\TypedContact;
use Horde\Rdo\TypeSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ArrayAccess;

#[CoversClass(DefaultHydrator::class)]
#[CoversClass(FieldBagHydrator::class)]
class HydratorTest extends TestCase
{
    private function contactSchema(): TypeSchema
    {
        return (new TypeSchema(TypedContact::class, 'contacts'))
            ->id('id', FieldType::INT)
            ->field('name', FieldType::STRING)
            ->field('email', FieldType::STRING, nullable: true);
    }

    // --- DefaultHydrator ---

    public function testDefaultHydratorHydrates(): void
    {
        $hydrator = new DefaultHydrator();
        $schema = $this->contactSchema();

        $entity = $hydrator->hydrate(
            ['id' => '42', 'name' => 'Alice', 'email' => 'alice@example.com'],
            $schema,
        );

        $this->assertInstanceOf(TypedContact::class, $entity);
        $this->assertSame(42, $entity->id);
        $this->assertSame('Alice', $entity->name);
        $this->assertSame('alice@example.com', $entity->email);
    }

    public function testDefaultHydratorCastsInt(): void
    {
        $hydrator = new DefaultHydrator();
        $schema = $this->contactSchema();

        $entity = $hydrator->hydrate(['id' => '7', 'name' => 'Bob'], $schema);

        $this->assertSame(7, $entity->id);
    }

    public function testDefaultHydratorHandlesNullableDefault(): void
    {
        $hydrator = new DefaultHydrator();
        $schema = $this->contactSchema();

        $entity = $hydrator->hydrate(['id' => '1', 'name' => 'Eve'], $schema);

        $this->assertNull($entity->email);
    }

    public function testDefaultHydratorExtracts(): void
    {
        $hydrator = new DefaultHydrator();
        $schema = $this->contactSchema();
        $entity = new TypedContact(42, 'Alice', 'alice@example.com');

        $data = $hydrator->extract($entity, $schema);

        $this->assertSame(42, $data['id']);
        $this->assertSame('Alice', $data['name']);
        $this->assertSame('alice@example.com', $data['email']);
    }

    public function testDefaultHydratorColumnMapping(): void
    {
        $schema = (new TypeSchema(TypedContact::class, 'contacts'))
            ->id('id', FieldType::INT, 'contact_id')
            ->field('name', FieldType::STRING, column: 'full_name')
            ->field('email', FieldType::STRING, nullable: true);

        $hydrator = new DefaultHydrator();

        // Hydrate with column names
        $entity = $hydrator->hydrate(
            ['contact_id' => '5', 'full_name' => 'Charlie', 'email' => null],
            $schema,
        );

        $this->assertSame(5, $entity->id);
        $this->assertSame('Charlie', $entity->name);

        // Extract uses column names
        $data = $hydrator->extract($entity, $schema);
        $this->assertArrayHasKey('contact_id', $data);
        $this->assertArrayHasKey('full_name', $data);
    }

    public function testDefaultHydratorBoolCasting(): void
    {
        $schema = (new TypeSchema(BoolEntity::class, 't'))
            ->field('active', FieldType::BOOL);

        $hydrator = new DefaultHydrator();
        $entity = $hydrator->hydrate(['active' => '1'], $schema);

        $this->assertTrue($entity->active);

        $data = $hydrator->extract($entity, $schema);
        $this->assertSame(1, $data['active']);
    }

    public function testDefaultHydratorDatetimeCasting(): void
    {
        $schema = (new TypeSchema(DateEntity::class, 't'))
            ->field('created', FieldType::DATETIME);

        $hydrator = new DefaultHydrator();
        $entity = $hydrator->hydrate(['created' => '2026-04-10 12:00:00'], $schema);

        $this->assertInstanceOf(DateTimeImmutable::class, $entity->created);
        $this->assertSame('2026-04-10', $entity->created->format('Y-m-d'));

        $data = $hydrator->extract($entity, $schema);
        $this->assertSame('2026-04-10 12:00:00', $data['created']);
    }

    public function testDefaultHydratorJsonCasting(): void
    {
        $schema = (new TypeSchema(JsonEntity::class, 't'))
            ->field('data', FieldType::JSON);

        $hydrator = new DefaultHydrator();
        $entity = $hydrator->hydrate(['data' => '{"foo":"bar"}'], $schema);

        $this->assertSame(['foo' => 'bar'], $entity->data);

        $data = $hydrator->extract($entity, $schema);
        $this->assertSame('{"foo":"bar"}', $data['data']);
    }

    public function testDefaultHydratorFloatCasting(): void
    {
        $schema = (new TypeSchema(FloatEntity::class, 't'))
            ->field('price', FieldType::FLOAT);

        $hydrator = new DefaultHydrator();
        $entity = $hydrator->hydrate(['price' => '12.99'], $schema);

        $this->assertSame(12.99, $entity->price);
    }

    public function testDefaultHydratorSerializedCasting(): void
    {
        $schema = (new TypeSchema(SerializedEntity::class, 't'))
            ->field('data', FieldType::SERIALIZED);

        $original = ['key' => 'value', 'nested' => [1, 2]];
        $hydrator = new DefaultHydrator();
        $entity = $hydrator->hydrate(['data' => serialize($original)], $schema);

        $this->assertSame($original, $entity->data);

        $data = $hydrator->extract($entity, $schema);
        $this->assertSame(serialize($original), $data['data']);
    }

    public function testDefaultHydratorNullValues(): void
    {
        $hydrator = new DefaultHydrator();
        $schema = $this->contactSchema();

        $entity = $hydrator->hydrate(['id' => '1', 'name' => 'X', 'email' => null], $schema);
        $this->assertNull($entity->email);
    }

    public function testDefaultHydratorExtractSkipsUninitializedProperties(): void
    {
        // TypedContact has a required $name param — create one normally,
        // then check that extract handles it
        $hydrator = new DefaultHydrator();
        $schema = $this->contactSchema();

        $entity = new TypedContact(1, 'Test');
        $data = $hydrator->extract($entity, $schema);

        // email has a default of null, so it should be present
        $this->assertArrayHasKey('email', $data);
        $this->assertNull($data['email']);
    }

    public function testDefaultHydratorPassesThroughUnmappedColumns(): void
    {
        $hydrator = new DefaultHydrator();
        $schema = $this->contactSchema();

        // 'extra_col' is not in the schema — should still hydrate without error
        $entity = $hydrator->hydrate(
            ['id' => '1', 'name' => 'Alice', 'extra_col' => 'ignored'],
            $schema,
        );

        $this->assertSame(1, $entity->id);
        $this->assertSame('Alice', $entity->name);
    }

    public function testDefaultHydratorExtractSkipsMissingProperties(): void
    {
        // Schema references a field that doesn't exist on the entity
        $schema = (new TypeSchema(TypedContact::class, 'contacts'))
            ->id('id', FieldType::INT)
            ->field('name', FieldType::STRING)
            ->field('nonexistent', FieldType::STRING);

        $hydrator = new DefaultHydrator();
        $entity = new TypedContact(1, 'Test');

        $data = $hydrator->extract($entity, $schema);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayNotHasKey('nonexistent', $data);
    }

    // --- FieldBagHydrator ---

    public function testFieldBagHydratorHydrates(): void
    {
        $schema = (new TypeSchema(DynamicEntity::class, 't'))
            ->field('name', FieldType::STRING)
            ->field('email', FieldType::STRING);

        $hydrator = new FieldBagHydrator();
        $entity = $hydrator->hydrate(['name' => 'Dan', 'email' => 'dan@x.com'], $schema);

        $this->assertSame('Dan', $entity->name);
        $this->assertSame('dan@x.com', $entity->email);
    }

    public function testFieldBagHydratorExtracts(): void
    {
        $schema = (new TypeSchema(DynamicEntity::class, 't'))
            ->field('name', FieldType::STRING)
            ->field('email', FieldType::STRING);

        $entity = new DynamicEntity();
        $entity->name = 'Dan';
        $entity->email = 'dan@x.com';

        $hydrator = new FieldBagHydrator();
        $data = $hydrator->extract($entity, $schema);

        $this->assertSame('Dan', $data['name']);
        $this->assertSame('dan@x.com', $data['email']);
    }

    public function testFieldBagHydratorArrayAccessHydrates(): void
    {
        $schema = (new TypeSchema(ArrayAccessEntity::class, 't'))
            ->field('name', FieldType::STRING)
            ->field('email', FieldType::STRING);

        $hydrator = new FieldBagHydrator();
        $entity = $hydrator->hydrate(['name' => 'Eve', 'email' => 'eve@x.com'], $schema);

        $this->assertInstanceOf(ArrayAccessEntity::class, $entity);
        $this->assertSame('Eve', $entity['name']);
        $this->assertSame('eve@x.com', $entity['email']);
    }

    public function testFieldBagHydratorArrayAccessExtracts(): void
    {
        $schema = (new TypeSchema(ArrayAccessEntity::class, 't'))
            ->field('name', FieldType::STRING)
            ->field('email', FieldType::STRING);

        $entity = new ArrayAccessEntity();
        $entity['name'] = 'Eve';
        $entity['email'] = 'eve@x.com';

        $hydrator = new FieldBagHydrator();
        $data = $hydrator->extract($entity, $schema);

        $this->assertSame('Eve', $data['name']);
        $this->assertSame('eve@x.com', $data['email']);
    }
}

// --- Inline test entity stubs ---

class BoolEntity
{
    public bool $active = false;
}

class DateEntity
{
    public ?DateTimeImmutable $created = null;
}

class JsonEntity
{
    public array $data = [];
}

class FloatEntity
{
    public float $price = 0.0;
}

class SerializedEntity
{
    public mixed $data = null;
}

class DynamicEntity
{
    public string $name = '';
    public string $email = '';
}

class ArrayAccessEntity implements ArrayAccess
{
    private array $data = [];

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}
