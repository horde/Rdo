<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit\Fixtures;

use Horde\Rdo\Attribute\BelongsTo;
use Horde\Rdo\Attribute\Column;
use Horde\Rdo\Attribute\HasMany;
use Horde\Rdo\Attribute\Id;
use Horde\Rdo\Attribute\ManyToMany;
use Horde\Rdo\Attribute\Table;
use Horde\Rdo\FieldType;

#[Table(name: 'contacts', timestamps: true)]
class AnnotatedContact
{
    #[Id(type: FieldType::INT)]
    public int $id;

    #[Column]
    public string $name;

    #[Column(nullable: true)]
    public ?string $email = null;

    #[Column(name: 'phone_number', nullable: true)]
    public ?string $phone = null;

    #[Column(type: FieldType::TEXT, lazy: true)]
    public string $bio;

    #[BelongsTo(target: 'App\Entity\Company', foreignKey: 'company_id')]
    public ?object $company = null;

    #[HasMany(target: 'App\Entity\Order', foreignKey: 'contact_id')]
    public iterable $orders;

    #[ManyToMany(target: 'App\Entity\Group', through: 'contact_groups')]
    public iterable $groups;
}
