<?php

declare(strict_types=1);

/**
 * Copyright 2006-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @package  Rdo
 */

namespace Horde\Rdo;

/**
 * Backward-compatible alias for BaseMapper.
 *
 * Existing subclasses that extend MapperSql continue to work
 * identically — all logic lives in BaseMapper.
 *
 * @category Horde
 * @package  Rdo
 */
class MapperSql extends BaseMapper {}
