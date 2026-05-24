<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Identity;

use App\Households\Domain\IdentityGenerator;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\UuidV7;

final class Uuid7IdentityGenerator implements IdentityGenerator
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function nextHouseholdId(): HouseholdId
    {
        return HouseholdId::fromString(UuidV7::generate($this->clock->now()));
    }

    public function nextMemberId(): MemberId
    {
        return MemberId::fromString(UuidV7::generate($this->clock->now()));
    }
}
