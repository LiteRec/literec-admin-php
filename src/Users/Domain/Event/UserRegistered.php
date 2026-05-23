<?php

declare(strict_types=1);

namespace App\Users\Domain\Event;

use App\Users\Domain\ValueObject\UserId;
use App\Users\Domain\ValueObject\Username;
use DateTimeImmutable;

final readonly class UserRegistered
{
    public function __construct(
        public UserId $userId,
        public Username $username,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
