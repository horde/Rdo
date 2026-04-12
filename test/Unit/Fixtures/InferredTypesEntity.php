<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit\Fixtures;

use DateTimeImmutable;
use Horde\Rdo\Attribute\Column;
use Horde\Rdo\Attribute\HasOne;
use Horde\Rdo\Attribute\Id;
use Horde\Rdo\Attribute\Table;

/**
 * Entity fixture that relies on type inference rather than explicit FieldType.
 *
 * Used to test AttributeSchemaReader::inferFieldType() for int, float,
 * bool, array, DateTimeImmutable, and the default string fallback.
 */
#[Table(name: 'inferred')]
class InferredTypesEntity
{
    #[Id]
    public int $id;

    #[Column]
    public float $score;

    #[Column]
    public bool $active;

    #[Column]
    public array $tags;

    #[Column]
    public DateTimeImmutable $created;

    #[Column]
    public string $name;

    #[HasOne(target: 'App\Entity\Profile', foreignKey: 'user_id')]
    public ?object $profile = null;
}
