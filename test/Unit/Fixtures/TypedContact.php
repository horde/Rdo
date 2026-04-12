<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit\Fixtures;

/**
 * Typed entity with constructor promotion (for DefaultHydrator tests).
 */
class TypedContact
{
    public function __construct(
        public readonly int $id,
        public string $name,
        public ?string $email = null,
    ) {}
}
