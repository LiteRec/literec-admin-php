<?php

declare(strict_types=1);

namespace App\Users\Domain\Event;

use App\Users\Domain\ValueObject\UserId;
use DateTimeImmutable;

final readonly class PasswordChanged
{
    public function __construct(
        public UserId $userId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
