<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Fixtures;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * Deterministic clock used by fixture loads so {@see DateTimeImmutable}
 * columns (registered_at, updated_at, recorded_at, …) match byte-for-byte
 * across runs at the same seed.
 *
 * Mutable on purpose: {@see InventoryPurchaseOrdersFixtures} advances
 * the instant between "PO sent" and "PO received" so the timeline
 * column shows realistic spacing. This violates the general
 * "immutable by default" rule deliberately — the alternative is a new
 * clock instance per advance and complex DI re-wiring inside a fixture.
 * Use is intentionally restricted to fixture loaders and tests; never
 * bind this class to {@see ClockInterface} in production.
 */
final class FixedClock implements ClockInterface
{
    public const DEFAULT_INSTANT = '2025-01-15T12:00:00+00:00';

    private DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable(self::DEFAULT_INSTANT, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    /**
     * Advance the frozen instant by {@see $interval}. Used by purchase
     * order fixtures to simulate the gap between draft → sent → received.
     */
    public function advance(DateInterval $interval): void
    {
        $this->now = $this->now->add($interval);
    }

    /**
     * Reset the clock to a specific instant. Used by tests that need a
     * fresh starting point without re-instantiating the service.
     */
    public function reset(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }
}
