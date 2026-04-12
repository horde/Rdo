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
 * Abstract query condition contract.
 *
 * A Criterion is either a leaf (single condition) or a composite
 * (logical combination). The tree IS the query. Backend-specific
 * translation happens through the Visitor pattern.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
interface Criterion
{
    public function accept(CriterionVisitor $visitor): mixed;
}
