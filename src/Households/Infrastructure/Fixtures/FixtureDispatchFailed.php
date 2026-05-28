<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Fixtures;

use RuntimeException;

/**
 * Raised by {@see HouseholdsFixtures} when a command dispatched through
 * the bus during seeding does not yield the expected handler result.
 * This only ever fires for a programming error in the fixture wiring
 * (a missing handler or a handler returning the wrong type), so it is a
 * dev/test-only infrastructure failure rather than a domain exception.
 */
final class FixtureDispatchFailed extends RuntimeException
{
    public static function missingHandledStamp(string $expectedType): self
    {
        return new self(sprintf(
            'Expected HandledStamp on dispatched command (looking for %s).',
            $expectedType,
        ));
    }

    public static function unexpectedResultType(string $expectedType, string $actualType): self
    {
        return new self(sprintf(
            'Expected handler to return %s, got %s.',
            $expectedType,
            $actualType,
        ));
    }
}
