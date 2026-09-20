<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use App\Administration\Domain\ValueObject\RoleDescription;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use DateTimeImmutable;

final readonly class RoleDefined
{
    public function __construct(
        public RoleId $roleId,
        public RoleName $name,
        public RoleDescription $description,
        public PrivilegeSet $privileges,
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
