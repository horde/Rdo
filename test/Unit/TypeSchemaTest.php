<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit;

use Horde\Rdo\FieldDefinition;
use Horde\Rdo\FieldType;
use Horde\Rdo\RelationDefinition;
use Horde\Rdo\RelationType;
use Horde\Rdo\TypeSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeSchema::class)]
#[CoversClass(FieldDefinition::class)]
#[CoversClass(RelationDefinition::class)]
class TypeSchemaTest extends TestCase
{
    public function testConstructWithExplicitTable(): void
    {
        $schema = new TypeSchema('App\Entity\Contact', 'contacts');

        $this->assertSame('App\Entity\Contact', $schema->getEntityClass());
        $this->assertSame('contacts', $schema->getTable());
    }

    public function testConstructDerivesTableFromClassName(): void
    {
        $schema = new TypeSchema('App\Entity\Contact');

        $this->assertSame('contact', $schema->getTable());
    }

    public function testIdField(): void
    {
        $schema = (new TypeSchema('X', 't'))->id('id', FieldType::INT);

        $pk = $schema->getPrimaryKey();
        $this->assertNotNull($pk);
        $this->assertSame('id', $pk->name);
        $this->assertSame(FieldType::INT, $pk->type);
        $this->assertTrue($pk->primary);
    }

    public function testIdFieldWithCustomColumn(): void
    {
        $schema = (new TypeSchema('X', 't'))->id('id', FieldType::INT, 'contact_id');

        $pk = $schema->getPrimaryKey();
        $this->assertSame('contact_id', $pk->columnName());
    }

    public function testRegularField(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->field('name', FieldType::STRING)
            ->field('email', FieldType::STRING, nullable: true);

        $name = $schema->getField('name');
        $this->assertNotNull($name);
        $this->assertSame('name', $name->name);
        $this->assertSame(FieldType::STRING, $name->type);
        $this->assertFalse($name->nullable);

        $email = $schema->getField('email');
        $this->assertTrue($email->nullable);
    }

    public function testFieldWithCustomColumn(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->field('firstName', FieldType::STRING, column: 'first_name');

        $field = $schema->getField('firstName');
        $this->assertSame('first_name', $field->columnName());
    }

    public function testLazyField(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->field('name', FieldType::STRING)
            ->field('bio', FieldType::TEXT, lazy: true);

        $eager = $schema->getEagerFields();
        $this->assertCount(1, $eager);
        $this->assertArrayHasKey('name', $eager);
        $this->assertArrayNotHasKey('bio', $eager);
    }

    public function testGetFieldsReturnsAll(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->id()
            ->field('name', FieldType::STRING)
            ->field('email', FieldType::STRING);

        $this->assertCount(3, $schema->getFields());
    }

    public function testGetFieldReturnsNullForUnknown(): void
    {
        $schema = new TypeSchema('X', 't');
        $this->assertNull($schema->getField('nonexistent'));
    }

    public function testHasOneRelation(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->hasOne('profile', 'App\Entity\Profile', 'user_id');

        $rel = $schema->getRelation('profile');
        $this->assertNotNull($rel);
        $this->assertSame(RelationType::HAS_ONE, $rel->type);
        $this->assertSame('App\Entity\Profile', $rel->targetClass);
        $this->assertSame('user_id', $rel->foreignKey);
        $this->assertTrue($rel->lazy);
    }

    public function testHasManyRelation(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->hasMany('orders', 'App\Entity\Order', 'contact_id', lazy: false);

        $rel = $schema->getRelation('orders');
        $this->assertSame(RelationType::HAS_MANY, $rel->type);
        $this->assertFalse($rel->lazy);
    }

    public function testBelongsToRelation(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->belongsTo('company', 'App\Entity\Company', 'company_id');

        $rel = $schema->getRelation('company');
        $this->assertSame(RelationType::BELONGS_TO, $rel->type);
    }

    public function testManyToManyRelation(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->manyToMany('tags', 'App\Entity\Tag', 'contact_tags', 'contact_id');

        $rel = $schema->getRelation('tags');
        $this->assertSame(RelationType::MANY_TO_MANY, $rel->type);
        $this->assertSame('contact_tags', $rel->through);
        $this->assertSame('contact_id', $rel->foreignKey);
    }

    public function testGetRelationReturnsNullForUnknown(): void
    {
        $schema = new TypeSchema('X', 't');
        $this->assertNull($schema->getRelation('nonexistent'));
    }

    public function testGetRelationsReturnsAll(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->hasMany('orders', 'O')
            ->belongsTo('company', 'C');

        $this->assertCount(2, $schema->getRelations());
    }

    public function testColumnForField(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->field('firstName', FieldType::STRING, column: 'first_name');

        $this->assertSame('first_name', $schema->columnForField('firstName'));
    }

    public function testColumnForFieldFallsBackToFieldName(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->field('name', FieldType::STRING);

        $this->assertSame('name', $schema->columnForField('name'));
    }

    public function testColumnForFieldUnknownFieldReturnsFieldName(): void
    {
        $schema = new TypeSchema('X', 't');
        $this->assertSame('unknown', $schema->columnForField('unknown'));
    }

    public function testFieldForColumn(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->field('firstName', FieldType::STRING, column: 'first_name');

        $this->assertSame('firstName', $schema->fieldForColumn('first_name'));
    }

    public function testFieldForColumnReturnsNullForUnknown(): void
    {
        $schema = new TypeSchema('X', 't');
        $this->assertNull($schema->fieldForColumn('unknown'));
    }

    public function testTimestamps(): void
    {
        $schema = (new TypeSchema('X', 't'))->timestamps();
        $this->assertTrue($schema->hasTimestamps());
    }

    public function testTimestampsDefaultsFalse(): void
    {
        $schema = new TypeSchema('X', 't');
        $this->assertFalse($schema->hasTimestamps());
    }

    public function testGetPrimaryKeyReturnsNullWhenNoPkDefined(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->field('name', FieldType::STRING);

        $this->assertNull($schema->getPrimaryKey());
    }

    public function testHasOneRelationDefaultLazy(): void
    {
        $schema = (new TypeSchema('X', 't'))
            ->hasOne('profile', 'P');

        $rel = $schema->getRelation('profile');
        $this->assertTrue($rel->lazy);
        $this->assertNull($rel->foreignKey);
    }

    public function testFieldDefinitionColumnName(): void
    {
        $withColumn = new FieldDefinition('foo', FieldType::STRING, column: 'bar');
        $this->assertSame('bar', $withColumn->columnName());

        $withoutColumn = new FieldDefinition('foo', FieldType::STRING);
        $this->assertSame('foo', $withoutColumn->columnName());
    }

    public function testFluentFullSchema(): void
    {
        $schema = (new TypeSchema('App\Entity\Contact', 'contacts'))
            ->id('id', FieldType::INT)
            ->field('name', FieldType::STRING)
            ->field('email', FieldType::STRING, nullable: true)
            ->field('phone', FieldType::STRING, nullable: true)
            ->field('bio', FieldType::TEXT, lazy: true)
            ->hasMany('orders', 'App\Entity\Order', 'contact_id')
            ->belongsTo('company', 'App\Entity\Company', 'company_id')
            ->manyToMany('groups', 'App\Entity\Group', 'contact_groups')
            ->timestamps();

        $this->assertSame('contacts', $schema->getTable());
        $this->assertNotNull($schema->getPrimaryKey());
        $this->assertCount(5, $schema->getFields());
        $this->assertCount(4, $schema->getEagerFields());
        $this->assertCount(3, $schema->getRelations());
        $this->assertTrue($schema->hasTimestamps());
    }
}
