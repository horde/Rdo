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
 * Value object describing a single field in an entity's type schema.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final readonly class FieldDefinition
{
    /**
     * @param string    $name     Logical field name (as used in entity/criteria)
     * @param FieldType $type     Data type for casting and validation
     * @param bool      $primary  Whether this is the primary key field
     * @param bool      $nullable Whether null is a valid value
     * @param bool      $lazy     Whether to defer loading until accessed
     * @param ?string   $column   Backend column name (defaults to $name)
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public bool $primary = false,
        public bool $nullable = false,
        public bool $lazy = false,
        public ?string $column = null,
    ) {}

    /**
     * Return the backend column name (falls back to the logical field name).
     */
    public function columnName(): string
    {
        return $this->column ?? $this->name;
    }
}
