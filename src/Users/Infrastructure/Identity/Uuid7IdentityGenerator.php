<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Identity;

use App\Users\Domain\IdentityGenerator;
use App\Users\Domain\ValueObject\UserId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\UuidV7;

final class Uuid7IdentityGenerator implements IdentityGenerator
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function nextUserId(): UserId
    {
        return UserId::fromString(UuidV7::generate($this->clock->now()));
    }
}
