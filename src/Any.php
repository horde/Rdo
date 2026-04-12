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
 * OR compositor: at least one child must match.
 *
 * Usage:
 *   Any::of(
 *       Field::equals('role', 'admin'),
 *       Field::equals('role', 'editor'),
 *   )
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final class Any
{
    /** @codeCoverageIgnore */
    private function __construct() {}

    public static function of(Criterion ...$criteria): CompositeCriterion
    {
        return new CompositeCriterion(LogicalOperator::OR, $criteria);
    }
}
