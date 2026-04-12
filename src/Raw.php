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
 * Raw backend-specific criterion factory (escape hatch).
 *
 * Usage:
 *   Raw::sql('ST_DWithin(location, ST_MakePoint(?, ?), ?)', [$lng, $lat, $radius])
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final class Raw
{
    /** @codeCoverageIgnore */
    private function __construct() {}

    /**
     * @param string $expression Backend-native expression
     * @param array  $params     Bind parameters
     */
    public static function sql(string $expression, array $params = []): RawCriterion
    {
        return new RawCriterion($expression, $params);
    }
}
