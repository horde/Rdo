<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit;

use Horde\Rdo\AttributeSchemaReader;
use Horde\Rdo\FieldType;
use Horde\Rdo\RdoException;
use Horde\Rdo\RelationType;
use Horde\Rdo\Test\Unit\Fixtures\AnnotatedContact;
use Horde\Rdo\Test\Unit\Fixtures\InferredTypesEntity;
use Horde\Rdo\Test\Unit\Fixtures\PlainObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeSchemaReader::class)]
class AttributeSchemaReaderTest extends TestCase
{
    private AttributeSchemaReader $reader;

    protected function setUp(): void
    {
        $this->reader = new AttributeSchemaReader();
    }

    public function testReadsTableName(): void
    {
        $schema = $this->reader->read(AnnotatedContact::class);

        $this->assertSame('contacts', $schema->getTable());
        $this->assertSame(AnnotatedContact::class, $schema->getEntityClass());
    }

    public function testReadsTimestamps(): void
    {
        $schema = $this->reader->read(AnnotatedContact::class);
        $this->assertTrue($schema->hasTimestamps());
    }

    public function testReadsPrimaryKey(): void
    {
        $schema = $this->reader->read(AnnotatedContact::class);

        $pk = $schema->getPrimaryKey();
        $this->assertNotNull($pk);
        $this->assertSame('id', $pk->name);
        $this->assertSame(FieldType::INT, $pk->type);
        $this->assertTrue($pk->primary);
    }

    public function testReadsColumns(): void
    {
        $schema = $this->reader->read(AnnotatedContact::class);

        $name = $schema->getField('name');
        $this->assertNotNull($name);
        $this->assertSame(FieldType::STRING, $name->type);
        $this->assertFalse($name->nullable);

        $email = $schema->getField('email');
        $this->assertNotNull($email);
        $this->assertTrue($email->nullable);
    }

    public function testReadsCustomColumnName(): void
    {
        $schema = $this->reader->read(AnnotatedContact::class);

        $phone = $schema->getField('phone');
        $this->assertNotNull($phone);
        $this->assertSame('phone_number', $phone->columnName());
    }

    public function testReadsLazyColumn(): void
    {
        $schema = $this->reader->read(AnnotatedContact::class);

        $bio = $schema->getField('bio');
        $this->assertNotNull($bio);
        $this->assertTrue($bio->lazy);
        $this->assertSame(FieldType::TEXT, $bio->type);
    }

    public function testReadsExplicitFieldType(): void
    {
        $schema = $this->reader->read(AnnotatedContact::class);

        $id = $schema->getPrimaryKey();
        $this->assertSame(FieldType::INT, $id->type);
    }

    public function testReadsBelongsToRelation(): void
    {
        $schema = $this->reader->read(AnnotatedContact::class);

        $rel = $schema->getRelation('company');
        $this->assertNotNull($rel);
        $this->assertSame(RelationType::BELONGS_TO, $rel->type);
        $this->assertSame('App\Entity\Company', $rel->targetClass);
        $this->assertSame('company_id', $rel->foreignKey);
    }

    public function testReadsHasManyRelation(): void
    {
        $schema = $this->reader->read(AnnotatedContact::class);

        $rel = $schema->getRelation('orders');
        $this->assertNotNull($rel);
        $this->assertSame(RelationType::HAS_MANY, $rel->type);
        $this->assertSame('contact_id', $rel->foreignKey);
    }

    public function testReadsManyToManyRelation(): void
    {
        $schema = $this->reader->read(AnnotatedContact::class);

        $rel = $schema->getRelation('groups');
        $this->assertNotNull($rel);
        $this->assertSame(RelationType::MANY_TO_MANY, $rel->type);
        $this->assertSame('contact_groups', $rel->through);
    }

    public function testThrowsForMissingTableAttribute(): void
    {
        $this->expectException(RdoException::class);
        $this->expectExceptionMessageMatches('/missing the #\[Table\] attribute/');

        $this->reader->read(PlainObject::class);
    }

    public function testReadsHasOneRelation(): void
    {
        $schema = $this->reader->read(InferredTypesEntity::class);

        $rel = $schema->getRelation('profile');
        $this->assertNotNull($rel);
        $this->assertSame(RelationType::HAS_ONE, $rel->type);
        $this->assertSame('App\Entity\Profile', $rel->targetClass);
        $this->assertSame('user_id', $rel->foreignKey);
    }

    public function testInfersIntType(): void
    {
        $schema = $this->reader->read(InferredTypesEntity::class);

        $pk = $schema->getPrimaryKey();
        $this->assertSame(FieldType::INT, $pk->type);
    }

    public function testInfersFloatType(): void
    {
        $schema = $this->reader->read(InferredTypesEntity::class);

        $field = $schema->getField('score');
        $this->assertSame(FieldType::FLOAT, $field->type);
    }

    public function testInfersBoolType(): void
    {
        $schema = $this->reader->read(InferredTypesEntity::class);

        $field = $schema->getField('active');
        $this->assertSame(FieldType::BOOL, $field->type);
    }

    public function testInfersArrayAsJsonType(): void
    {
        $schema = $this->reader->read(InferredTypesEntity::class);

        $field = $schema->getField('tags');
        $this->assertSame(FieldType::JSON, $field->type);
    }

    public function testInfersDateTimeImmutableType(): void
    {
        $schema = $this->reader->read(InferredTypesEntity::class);

        $field = $schema->getField('created');
        $this->assertSame(FieldType::DATETIME, $field->type);
    }

    public function testInfersStringType(): void
    {
        $schema = $this->reader->read(InferredTypesEntity::class);

        $field = $schema->getField('name');
        $this->assertSame(FieldType::STRING, $field->type);
    }
}
