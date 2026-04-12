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
 * Relationship existence factory.
 *
 * Usage:
 *   Has::relation('orders')
 *   Has::relation('orders', Field::greaterThan('amount', 100))
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final class Has
{
    /** @codeCoverageIgnore */
    private function __construct() {}

    public static function relation(string $name, ?Criterion $condition = null): RelationCriterion
    {
        return new RelationCriterion($name, $condition);
    }
}
