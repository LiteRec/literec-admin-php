<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidDateOfBirth;
use DateMalformedStringException;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A member's date of birth.
 *
 * Three factories cover the three call-sites:
 *   - {@see self::of()} takes an already-parsed DateTimeImmutable and
 *     validates not-future. Use from code that already has a parsed value
 *     in hand.
 *   - {@see self::fromString()} parses an ISO string and trusts the caller —
 *     no future check. Use from persistence hydration (Doctrine types,
 *     fixtures) where the value was validated at write time.
 *   - {@see self::parse()} parses an ISO string AND validates not-future.
 *     Use from command handlers receiving raw input from the outside world.
 *
 * Value objects normally do not depend on a clock; encapsulating the
 * not-future rule here keeps the invariant adjacent to the data while still
 * letting the value object itself stay pure (the clock is only ever a
 * parameter, never a stored field).
 */
final readonly class DateOfBirth
{
    public DateTimeImmutable $value;

    private function __construct(DateTimeImmutable $value)
    {
        $this->value = $value;
    }

    public static function of(DateTimeImmutable $value, ClockInterface $clock): self
    {
        if ($value > $clock->now()) {
            throw InvalidDateOfBirth::future();
        }

        return new self($value);
    }

    public static function fromString(string $iso): self
    {
        return new self(self::parseIso($iso));
    }

    /**
     * Convenience factory for command handlers receiving an ISO date string
     * from the outside world: parses safely (wraps malformed input in
     * {@see InvalidDateOfBirth::malformed()}) and then validates against the
     * not-future invariant using the injected clock.
     */
    public static function parse(string $iso, ClockInterface $clock): self
    {
        return self::of(self::parseIso($iso), $clock);
    }

    private static function parseIso(string $iso): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($iso);
        } catch (DateMalformedStringException) {
            throw InvalidDateOfBirth::malformed($iso);
        }
    }

    public function value(): DateTimeImmutable
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value == $other->value;
    }
}
