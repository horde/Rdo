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
 * Leaf criterion: a single field comparison.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final readonly class FieldCriterion implements Criterion
{
    public function __construct(
        public string $field,
        public Operator $operator,
        public mixed $value,
    ) {}

    public function accept(CriterionVisitor $visitor): mixed
    {
        return $visitor->visitField($this);
    }
}
