<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Identity;

use App\Catalog\Domain\IdentityGenerator;
use App\Catalog\Domain\ValueObject\ListingId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\UuidV7;

final class Uuid7IdentityGenerator implements IdentityGenerator
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function nextListingId(): ListingId
    {
        return ListingId::fromString(UuidV7::generate($this->clock->now()));
    }
}
