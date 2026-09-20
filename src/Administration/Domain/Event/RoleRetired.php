<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\RoleId;
use DateTimeImmutable;

final readonly class RoleRetired
{
    public function __construct(
        public RoleId $roleId,
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
