<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 */

namespace Horde\Rdo;

use Horde\Rdo\Attribute\BelongsTo;
use Horde\Rdo\Attribute\Column;
use Horde\Rdo\Attribute\HasMany;
use Horde\Rdo\Attribute\HasOne;
use Horde\Rdo\Attribute\Id;
use Horde\Rdo\Attribute\ManyToMany;
use Horde\Rdo\Attribute\Table;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Builds a TypeSchema from PHP 8 attributes on an entity class.
 *
 * Usage:
 *   $reader = new AttributeSchemaReader();
 *   $schema = $reader->read(Contact::class);
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final class AttributeSchemaReader
{
    /**
     * Build a TypeSchema from the attributes on $className.
     *
     * @param class-string $className
     * @throws RdoException If required #[Table] attribute is missing
     */
    public function read(string $className): TypeSchema
    {
        $ref = new ReflectionClass($className);
        $tableAttr = $this->getClassAttribute($ref, Table::class);

        if ($tableAttr === null) {
            throw new RdoException(sprintf(
                'Class %s is missing the #[Table] attribute.',
                $className,
            ));
        }

        $schema = new TypeSchema($className, $tableAttr->name);

        if ($tableAttr->timestamps) {
            $schema->timestamps();
        }

        foreach ($ref->getProperties() as $prop) {
            $this->processProperty($schema, $prop);
        }

        return $schema;
    }

    private function processProperty(TypeSchema $schema, ReflectionProperty $prop): void
    {
        // Check for #[Id]
        $idAttr = $this->getPropertyAttribute($prop, Id::class);
        if ($idAttr !== null) {
            $type = $idAttr->type ?? $this->inferFieldType($prop);
            $schema->id($prop->getName(), $type, $idAttr->column);
            return;
        }

        // Check for #[Column]
        $colAttr = $this->getPropertyAttribute($prop, Column::class);
        if ($colAttr !== null) {
            $type = $colAttr->type ?? $this->inferFieldType($prop);
            $schema->field(
                $prop->getName(),
                $type,
                nullable: $colAttr->nullable || $this->isNullableProperty($prop),
                lazy: $colAttr->lazy,
                column: $colAttr->name,
            );
            return;
        }

        // Check relationship attributes
        $hasOneAttr = $this->getPropertyAttribute($prop, HasOne::class);
        if ($hasOneAttr !== null) {
            $schema->hasOne($prop->getName(), $hasOneAttr->target, $hasOneAttr->foreignKey, $hasOneAttr->lazy);
            return;
        }

        $hasManyAttr = $this->getPropertyAttribute($prop, HasMany::class);
        if ($hasManyAttr !== null) {
            $schema->hasMany($prop->getName(), $hasManyAttr->target, $hasManyAttr->foreignKey, $hasManyAttr->lazy);
            return;
        }

        $belongsToAttr = $this->getPropertyAttribute($prop, BelongsTo::class);
        if ($belongsToAttr !== null) {
            $schema->belongsTo($prop->getName(), $belongsToAttr->target, $belongsToAttr->foreignKey, $belongsToAttr->lazy);
            return;
        }

        $m2mAttr = $this->getPropertyAttribute($prop, ManyToMany::class);
        if ($m2mAttr !== null) {
            $schema->manyToMany($prop->getName(), $m2mAttr->target, $m2mAttr->through, $m2mAttr->foreignKey, $m2mAttr->lazy);
        }
    }

    /**
     * Infer FieldType from a property's PHP type declaration.
     */
    private function inferFieldType(ReflectionProperty $prop): FieldType
    {
        $type = $prop->getType();
        if (!$type instanceof ReflectionNamedType) {
            return FieldType::STRING;
        }

        return match ($type->getName()) {
            'int' => FieldType::INT,
            'float' => FieldType::FLOAT,
            'bool' => FieldType::BOOL,
            'array' => FieldType::JSON,
            'DateTimeInterface', 'DateTime', 'DateTimeImmutable' => FieldType::DATETIME,
            default => FieldType::STRING,
        };
    }

    private function isNullableProperty(ReflectionProperty $prop): bool
    {
        $type = $prop->getType();
        return $type !== null && $type->allowsNull();
    }

    /**
     * @template T of object
     * @param ReflectionClass<object> $ref
     * @param class-string<T> $attributeClass
     * @return T|null
     */
    private function getClassAttribute(ReflectionClass $ref, string $attributeClass): ?object
    {
        $attrs = $ref->getAttributes($attributeClass);
        if (empty($attrs)) {
            return null;
        }
        return $attrs[0]->newInstance();
    }

    /**
     * @template T of object
     * @param class-string<T> $attributeClass
     * @return T|null
     */
    private function getPropertyAttribute(ReflectionProperty $prop, string $attributeClass): ?object
    {
        $attrs = $prop->getAttributes($attributeClass);
        if (empty($attrs)) {
            return null;
        }
        return $attrs[0]->newInstance();
    }
}
