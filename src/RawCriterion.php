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
 * Raw backend-specific criterion (escape hatch).
 *
 * Carries a backend-native expression (e.g., SQL fragment) that the
 * visitor passes through directly. Opting into this explicitly
 * sacrifices backend portability for that criterion.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final readonly class RawCriterion implements Criterion
{
    /**
     * @param string $expression Backend-native expression (e.g., SQL fragment)
     * @param array  $params     Bind parameters for the expression
     */
    public function __construct(
        public string $expression,
        public array $params = [],
    ) {}

    public function accept(CriterionVisitor $visitor): mixed
    {
        return $visitor->visitRaw($this);
    }
}
