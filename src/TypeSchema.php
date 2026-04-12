<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 */

namespace Horde\Rdo;

/**
 * Describes an entity's structure: fields, relationships, and table mapping.
 *
 * Built either via the fluent API or from PHP attributes using
 * AttributeSchemaReader. Mutable during construction, then treated
 * as effectively immutable once registered.
 *
 * Usage (fluent):
 *   $schema = (new TypeSchema(Contact::class, 'contacts'))
 *       ->id('id', FieldType::INT)
 *       ->field('name', FieldType::STRING)
 *       ->field('email', FieldType::STRING)
 *       ->field('phone', FieldType::STRING, nullable: true)
 *       ->hasMany('orders', Order::class, 'contact_id')
 *       ->belongsTo('company', Company::class, 'company_id')
 *       ->timestamps();
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final class TypeSchema
{
    /** @var array<string, FieldDefinition> keyed by field name */
    private array $fields = [];

    /** @var array<string, RelationDefinition> keyed by relation name */
    private array $relations = [];

    private bool $timestamps = false;

    /**
     * @param string  $entityClass Fully-qualified entity class name
     * @param ?string $table       Backend table name (defaults to entity short name, lowercased)
     */
    public function __construct(
        private string $entityClass,
        private ?string $table = null,
    ) {
        if ($this->table === null) {
            $parts = explode('\\', $entityClass);
            $this->table = strtolower(end($parts));
        }
    }

    /**
     * Define the primary key field.
     */
    public function id(
        string $name = 'id',
        FieldType $type = FieldType::INT,
        ?string $column = null,
    ): self {
        $this->fields[$name] = new FieldDefinition(
            name: $name,
            type: $type,
            primary: true,
            column: $column,
        );
        return $this;
    }

    /**
     * Define a regular field.
     */
    public function field(
        string $name,
        FieldType $type,
        bool $nullable = false,
        bool $lazy = false,
        ?string $column = null,
    ): self {
        $this->fields[$name] = new FieldDefinition(
            name: $name,
            type: $type,
            nullable: $nullable,
            lazy: $lazy,
            column: $column,
        );
        return $this;
    }

    public function hasOne(
        string $name,
        string $target,
        ?string $foreignKey = null,
        bool $lazy = true,
    ): self {
        $this->relations[$name] = new RelationDefinition(
            name: $name,
            type: RelationType::HAS_ONE,
            targetClass: $target,
            foreignKey: $foreignKey,
            lazy: $lazy,
        );
        return $this;
    }

    public function hasMany(
        string $name,
        string $target,
        ?string $foreignKey = null,
        bool $lazy = true,
    ): self {
        $this->relations[$name] = new RelationDefinition(
            name: $name,
            type: RelationType::HAS_MANY,
            targetClass: $target,
            foreignKey: $foreignKey,
            lazy: $lazy,
        );
        return $this;
    }

    public function belongsTo(
        string $name,
        string $target,
        ?string $foreignKey = null,
        bool $lazy = true,
    ): self {
        $this->relations[$name] = new RelationDefinition(
            name: $name,
            type: RelationType::BELONGS_TO,
            targetClass: $target,
            foreignKey: $foreignKey,
            lazy: $lazy,
        );
        return $this;
    }

    public function manyToMany(
        string $name,
        string $target,
        string $through,
        ?string $foreignKey = null,
        bool $lazy = true,
    ): self {
        $this->relations[$name] = new RelationDefinition(
            name: $name,
            type: RelationType::MANY_TO_MANY,
            targetClass: $target,
            foreignKey: $foreignKey,
            through: $through,
            lazy: $lazy,
        );
        return $this;
    }

    public function timestamps(bool $enabled = true): self
    {
        $this->timestamps = $enabled;
        return $this;
    }

    // --- Accessors ---

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getPrimaryKey(): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->primary) {
                return $field;
            }
        }
        return null;
    }

    public function getField(string $name): ?FieldDefinition
    {
        return $this->fields[$name] ?? null;
    }

    /**
     * @return array<string, FieldDefinition>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Return only eagerly loaded (non-lazy) fields.
     *
     * @return array<string, FieldDefinition>
     */
    public function getEagerFields(): array
    {
        return array_filter($this->fields, fn(FieldDefinition $f) => !$f->lazy);
    }

    public function getRelation(string $name): ?RelationDefinition
    {
        return $this->relations[$name] ?? null;
    }

    /**
     * @return array<string, RelationDefinition>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Map a logical field name to its backend column name.
     */
    public function columnForField(string $fieldName): string
    {
        $field = $this->fields[$fieldName] ?? null;
        if ($field === null) {
            return $fieldName;
        }
        return $field->columnName();
    }

    /**
     * Map a backend column name to its logical field name.
     */
    public function fieldForColumn(string $columnName): ?string
    {
        foreach ($this->fields as $field) {
            if ($field->columnName() === $columnName) {
                return $field->name;
            }
        }
        return null;
    }

    public function hasTimestamps(): bool
    {
        return $this->timestamps;
    }
}
