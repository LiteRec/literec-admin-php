<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidDateOfBirth;
use App\Households\Domain\ValueObject\DateOfBirth;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class DateOfBirthTest extends TestCase
{
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
    }

    #[Test]
    #[TestDox('::of() accepts a past date.')]
    public function of_accepts_past_date(): void
    {
        $value = new DateTimeImmutable('1990-05-12');

        $dob = DateOfBirth::of($value, $this->clock);

        self::assertEquals($value, $dob->value());
    }

    #[Test]
    #[TestDox('::of() accepts a date equal to the current clock instant.')]
    public function of_accepts_present_date(): void
    {
        $dob = DateOfBirth::of($this->clock->now(), $this->clock);

        self::assertEquals($this->clock->now(), $dob->value());
    }

    #[Test]
    #[TestDox('::of() rejects a future date with InvalidDateOfBirth.')]
    public function of_rejects_future_date(): void
    {
        $this->expectException(InvalidDateOfBirth::class);

        DateOfBirth::of(new DateTimeImmutable('2999-01-01'), $this->clock);
    }

    #[Test]
    #[TestDox('::fromString() parses a valid ISO date without validating against a clock.')]
    public function from_string_parses_valid_iso(): void
    {
        $dob = DateOfBirth::fromString('1990-05-12');

        self::assertEquals(new DateTimeImmutable('1990-05-12'), $dob->value());
    }

    #[Test]
    #[TestDox('::fromString() accepts a future date (no clock check; caller-trusted).')]
    public function from_string_accepts_future_dates(): void
    {
        $dob = DateOfBirth::fromString('2999-01-01');

        self::assertEquals(new DateTimeImmutable('2999-01-01'), $dob->value());
    }

    #[Test]
    #[TestDox('::fromString() rejects a malformed string with InvalidDateOfBirth.')]
    public function from_string_rejects_malformed(): void
    {
        $this->expectException(InvalidDateOfBirth::class);

        DateOfBirth::fromString('not-a-date');
    }

    #[Test]
    #[TestDox('Equals another DateOfBirth with the same instant.')]
    public function equals(): void
    {
        $a = DateOfBirth::fromString('1990-05-12');
        $b = DateOfBirth::fromString('1990-05-12');
        $c = DateOfBirth::fromString('1990-05-13');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
