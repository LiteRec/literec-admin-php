<?php

declare(strict_types=1);

namespace App\Fixture\Domain;

use Symfony\Component\Clock\Clock as SymfonyClock;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;
use function Symfony\Component\Clock\now;

interface Clock
{
    public function now(): \DateTimeImmutable;
}

interface IdentityGenerator
{
    public function nextUserId(): string;
}

final class AmbientCallsExample
{
    public function __construct(private readonly Clock $clock, private readonly IdentityGenerator $ids)
    {
    }

    public function ambientCalls(): void
    {
        time();
        \date('Y');
        strtotime('+1 day');
        mktime(0);
        uniqid();
        random_int(1, 9);
        array_rand([1]);
        now();
        Uuid::v7();
        UuidV7::generate();
        SymfonyClock::get();
    }

    public function portCalls(): void
    {
        $this->clock->now();
        $this->ids->nextUserId();
    }
}

namespace App\Fixture\Infrastructure;

/**
 * Infrastructure is allowed to touch the ambient clock; only Domain and
 * Application are gated, so this call is not reported.
 */
final class InfrastructureClock
{
    public function now(): int
    {
        return time();
    }
}
