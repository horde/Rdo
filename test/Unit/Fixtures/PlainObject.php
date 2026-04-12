<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit\Fixtures;

/**
 * Simple entity without #[Table] attribute — for testing reader error handling.
 */
class PlainObject
{
    public string $name = '';
}
