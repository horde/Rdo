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
 * Value object describing a relationship between two entity types.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final readonly class RelationDefinition
{
    /**
     * @param string       $name        Logical relationship name
     * @param RelationType $type        Relationship cardinality
     * @param string       $targetClass Target entity class name
     * @param ?string      $foreignKey  Foreign key column name
     * @param ?string      $through     Pivot table name (for MANY_TO_MANY)
     * @param bool         $lazy        Whether to defer loading until accessed
     */
    public function __construct(
        public string $name,
        public RelationType $type,
        public string $targetClass,
        public ?string $foreignKey = null,
        public ?string $through = null,
        public bool $lazy = true,
    ) {}
}
