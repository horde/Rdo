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
 * Maps a property to a backend column.
 *
 * If $type is null, the type is auto-detected from the property's
 * PHP type declaration.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Column
{
    public function __construct(
        public ?FieldType $type = null,
        public ?string $name = null,
        public bool $nullable = false,
        public bool $lazy = false,
    ) {}
}
