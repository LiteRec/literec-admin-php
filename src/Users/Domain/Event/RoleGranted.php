<?php

declare(strict_types=1);

namespace App\Users\Domain\Event;

use App\Users\Domain\ValueObject\Role;
use App\Users\Domain\ValueObject\UserId;
use DateTimeImmutable;

final readonly class RoleGranted
{
    public function __construct(
        public UserId $userId,
        public Role $role,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
