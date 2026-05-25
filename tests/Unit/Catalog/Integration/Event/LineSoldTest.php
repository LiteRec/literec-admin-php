<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Integration\Event;

use App\Catalog\Integration\Event\LineSold;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class LineSoldTest extends TestCase
{
    #[Test]
    #[TestDox('Constructs with primitive-only fields exposed as readonly properties.')]
    public function constructs_with_primitive_fields(): void
    {
        $occurred = new DateTimeImmutable('2026-05-25 14:00:00');

        $event = new LineSold(
            listingId: '019571bf-5d51-7000-b500-000000000010',
            listingKind: 'PROGRAM',
            listingCode: 'YOGA-101',
            quantity: 2,
            facilityCode: 'MAIN',
            transactionId: 'TXN-0001',
            occurredAt: $occurred,
        );

        self::assertSame('019571bf-5d51-7000-b500-000000000010', $event->listingId);
        self::assertSame('PROGRAM', $event->listingKind);
        self::assertSame('YOGA-101', $event->listingCode);
        self::assertSame(2, $event->quantity);
        self::assertSame('MAIN', $event->facilityCode);
        self::assertSame('TXN-0001', $event->transactionId);
        self::assertSame($occurred, $event->occurredAt);
    }

    #[Test]
    #[TestWith(['', 'PROGRAM', 'YOGA-101', 'MAIN', 'TXN-1'], 'empty listingId')]
    #[TestWith(['lid', '', 'YOGA-101', 'MAIN', 'TXN-1'], 'empty listingKind')]
    #[TestWith(['lid', 'PROGRAM', '', 'MAIN', 'TXN-1'], 'empty listingCode')]
    #[TestWith(['lid', 'PROGRAM', 'YOGA-101', '', 'TXN-1'], 'empty facilityCode')]
    #[TestWith(['lid', 'PROGRAM', 'YOGA-101', 'MAIN', ''], 'empty transactionId')]
    #[TestDox('Rejects empty required string fields: $_dataName.')]
    public function rejects_empty_required_strings(
        string $listingId,
        string $listingKind,
        string $listingCode,
        string $facilityCode,
        string $transactionId,
    ): void {
        $this->expectException(InvalidArgumentException::class);

        $event = new LineSold(
            listingId: $listingId,
            listingKind: $listingKind,
            listingCode: $listingCode,
            quantity: 1,
            facilityCode: $facilityCode,
            transactionId: $transactionId,
            occurredAt: new DateTimeImmutable('2026-05-25 14:00:00'),
        );
        self::fail(sprintf('Expected exception was not thrown; got %s.', $event::class));
    }

    #[Test]
    #[TestWith([0], 'zero quantity')]
    #[TestWith([-1], 'negative quantity')]
    #[TestDox('Rejects non-positive quantity: $_dataName.')]
    public function rejects_non_positive_quantity(int $quantity): void
    {
        $this->expectException(InvalidArgumentException::class);

        $event = new LineSold(
            listingId: 'lid',
            listingKind: 'PROGRAM',
            listingCode: 'YOGA-101',
            quantity: $quantity,
            facilityCode: 'MAIN',
            transactionId: 'TXN-1',
            occurredAt: new DateTimeImmutable('2026-05-25 14:00:00'),
        );
        self::fail(sprintf('Expected exception was not thrown; got %s.', $event::class));
    }
}
