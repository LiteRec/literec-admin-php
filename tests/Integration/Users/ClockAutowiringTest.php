<?php

declare(strict_types=1);

namespace App\Tests\Integration\Users;

use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Medium]
final class ClockAutowiringTest extends KernelTestCase
{
    #[Test]
    #[TestDox('Psr\Clock\ClockInterface is autowired by the container and returns a present moment.')]
    public function psr_clock_interface_is_autowired_and_returns_a_present_moment(): void
    {
        $clock = static::getContainer()->get('clock');

        self::assertInstanceOf(ClockInterface::class, $clock);

        $before = microtime(true);
        $now = $clock->now();
        $after = microtime(true);

        $nowEpoch = (float) $now->format('U.u');

        // The lower-bound margin absorbs the floating-point conversion drift
        // between microtime(true) and \DateTimeImmutable::format('U.u')
        // (the latter rounds the microsecond fraction differently). The
        // upper-bound margin tolerates CI scheduler hiccups between
        // $clock->now() and the second microtime() call. 100 ms is
        // comfortably larger than both while still catching a clock that
        // returned a clearly stale or future timestamp.
        self::assertGreaterThanOrEqual($before - 0.1, $nowEpoch);
        self::assertLessThanOrEqual($after + 0.1, $nowEpoch);
    }

    #[Test]
    #[TestDox('App\Users\Domain\IdentityGenerator is autowired to the UUID v7 adapter.')]
    public function identity_generator_is_autowired_to_uuid_v7_adapter(): void
    {
        $generator = static::getContainer()->get(\App\Users\Domain\IdentityGenerator::class);

        self::assertInstanceOf(\App\Users\Infrastructure\Identity\Uuid7IdentityGenerator::class, $generator);
    }
}
