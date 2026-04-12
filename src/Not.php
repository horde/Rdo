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
 * Negation factory: inverts the inner criterion.
 *
 * Usage:
 *   Not::of(Field::isNull('email'))
 *
 * Note: this class shadows PHP's built-in `not` keyword only as a
 * fully-qualified name; within this namespace it is `Horde\Rdo\Not`.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final class Not
{
    /** @codeCoverageIgnore */
    private function __construct() {}

    public static function of(Criterion $criterion): NotCriterion
    {
        return new NotCriterion($criterion);
    }
}
