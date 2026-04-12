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
 * Relationship existence criterion.
 *
 * Expresses "entities that have (or have matching) related entities."
 * The visitor resolves the relationship name via TypeSchema and
 * generates appropriate backend queries (e.g., EXISTS subquery for SQL).
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final readonly class RelationCriterion implements Criterion
{
    public function __construct(
        public string $relation,
        public ?Criterion $condition = null,
    ) {}

    public function accept(CriterionVisitor $visitor): mixed
    {
        return $visitor->visitRelation($this);
    }
}
