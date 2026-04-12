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
 * Negation criterion: wraps an inner criterion with NOT.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final readonly class NotCriterion implements Criterion
{
    public function __construct(
        public Criterion $inner,
    ) {}

    public function accept(CriterionVisitor $visitor): mixed
    {
        return $visitor->visitNot($this);
    }
}
