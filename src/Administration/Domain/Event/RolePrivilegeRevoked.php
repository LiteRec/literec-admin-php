<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\Privilege;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\RoleId;
use DateTimeImmutable;

final readonly class RolePrivilegeRevoked
{
    public function __construct(
        public RoleId $roleId,
        public Privilege $privilege,
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
