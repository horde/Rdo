<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 */

namespace Horde\Rdo\Attribute;

use Attribute;
use Horde\Rdo\FieldType;

/**
 * Marks a property as the primary key.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Id
{
    public function __construct(
        public ?FieldType $type = null,
        public ?string $column = null,
    ) {}
}
