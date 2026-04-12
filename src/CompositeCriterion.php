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
 * Composite criterion: AND/OR grouping of child criteria.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final readonly class CompositeCriterion implements Criterion
{
    /**
     * @param LogicalOperator $logic    AND or OR
     * @param Criterion[]     $children Child criteria
     */
    public function __construct(
        public LogicalOperator $logic,
        public array $children,
    ) {}

    public function accept(CriterionVisitor $visitor): mixed
    {
        return $visitor->visitComposite($this);
    }
}
