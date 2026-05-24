<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidDateRange;
use App\Households\Domain\ValueObject\DateRange;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class DateRangeTest extends TestCase
{
    #[Test]
    #[TestDox('Constructs an open-ended range when "to" is null.')]
    public function constructs_open_ended(): void
    {
        $r = DateRange::of(new DateTimeImmutable('2024-01-01'));

        self::assertNull($r->to);
    }

    #[Test]
    #[TestDox('Constructs a closed range when "to" is greater than or equal to "from".')]
    public function constructs_closed_range(): void
    {
        $from = new DateTimeImmutable('2024-01-01');
        $to = new DateTimeImmutable('2024-12-31');

        $r = DateRange::of($from, $to);

        self::assertEquals($from, $r->from);
        self::assertEquals($to, $r->to);
    }

    #[Test]
    #[TestDox('Rejects a range whose "to" is earlier than its "from".')]
    public function rejects_end_before_start(): void
    {
        $this->expectException(InvalidDateRange::class);

        DateRange::of(
            new DateTimeImmutable('2024-12-31'),
            new DateTimeImmutable('2024-01-01'),
        );
    }

    #[Test]
    #[TestDox('::contains() returns true for instants between from and to (inclusive).')]
    public function contains_within_closed_range(): void
    {
        $r = DateRange::of(
            new DateTimeImmutable('2024-01-01'),
            new DateTimeImmutable('2024-12-31'),
        );

        self::assertTrue($r->contains(new DateTimeImmutable('2024-06-15')));
        self::assertTrue($r->contains(new DateTimeImmutable('2024-01-01')));
        self::assertTrue($r->contains(new DateTimeImmutable('2024-12-31')));
        self::assertFalse($r->contains(new DateTimeImmutable('2023-12-31')));
        self::assertFalse($r->contains(new DateTimeImmutable('2025-01-01')));
    }

    #[Test]
    #[TestDox('::contains() returns true for any instant on or after "from" when open-ended.')]
    public function contains_open_ended(): void
    {
        $r = DateRange::of(new DateTimeImmutable('2024-01-01'));

        self::assertTrue($r->contains(new DateTimeImmutable('2999-01-01')));
        self::assertFalse($r->contains(new DateTimeImmutable('2023-12-31')));
    }
}
