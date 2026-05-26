<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Inventory\Domain\Event\ItemLinked;
use App\Inventory\Domain\Event\ItemLinkUpdated;
use App\Inventory\Domain\Event\ItemUnlinked;
use App\Inventory\Domain\Exception\LinkToSelfForbidden;
use App\Inventory\Domain\ItemLink;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use App\Inventory\Domain\ValueObject\LinkViolation;
use App\Inventory\Domain\ValueObject\Quantity;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class ItemLinkTest extends TestCase
{
    private const LINK_ID = '019571bf-5d51-7000-b500-000000005001';
    private const MASTER_ID = '019571bf-5d51-7000-b500-000000005101';
    private const LINKED_ID = '019571bf-5d51-7000-b500-000000005102';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
    }

    #[Test]
    #[TestDox('::link() records ItemLinked with all configured constraint values.')]
    public function link_records_event(): void
    {
        $link = $this->makeLink(reservedQty: 5, unlimited: false, min: 1, max: 3, includeUntil: null);

        $events = $link->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(ItemLinked::class, $event);
        self::assertSame(5, $event->reservedQuantity->units);
        self::assertSame(1, $event->minRequired->units);
        self::assertSame(3, $event->maxPerPurchase->units);
        self::assertFalse($event->unlimited);
        self::assertNull($event->includeUntil);
    }

    #[Test]
    #[TestDox('::link() with the same master and linked item throws LinkToSelfForbidden.')]
    public function link_to_self_throws(): void
    {
        $this->expectException(LinkToSelfForbidden::class);

        ItemLink::link(
            ItemLinkId::fromString(self::LINK_ID),
            InventoryItemId::fromString(self::MASTER_ID),
            InventoryItemId::fromString(self::MASTER_ID),
            Quantity::zero(),
            true,
            Quantity::zero(),
            Quantity::zero(),
            null,
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::update() emits ItemLinkUpdated on change and short-circuits when unchanged.')]
    public function update_short_circuit(): void
    {
        $link = $this->makeLink(reservedQty: 5, unlimited: false, min: 1, max: 3, includeUntil: null);
        $link->releaseEvents();

        $link->update(
            Quantity::ofUnits(10),
            true,
            Quantity::ofUnits(2),
            Quantity::ofUnits(4),
            null,
            $this->clock,
        );
        $events = $link->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ItemLinkUpdated::class, $events[0]);

        // Re-applying the same values is a no-op.
        $link->update(
            Quantity::ofUnits(10),
            true,
            Quantity::ofUnits(2),
            Quantity::ofUnits(4),
            null,
            $this->clock,
        );
        self::assertSame([], $link->releaseEvents());
    }

    #[Test]
    #[TestDox('::unlink() records ItemUnlinked.')]
    public function unlink_records_event(): void
    {
        $link = $this->makeLink(reservedQty: 0, unlimited: true, min: 0, max: 0, includeUntil: null);
        $link->releaseEvents();

        $link->unlink($this->clock);

        $events = $link->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ItemUnlinked::class, $events[0]);
    }

    /**
     * @return Generator<string, array{
     *     reservedQty: int,
     *     unlimited: bool,
     *     min: int,
     *     max: int,
     *     includeUntil: ?DateTimeImmutable,
     *     now: DateTimeImmutable,
     *     masterQty: int,
     *     stockOnHand: int,
     *     reservedAlready: int,
     *     qtyInPurchase: int,
     *     expected: ?LinkViolation
     * }>
     */
    public static function violationCases(): Generator
    {
        $now = new DateTimeImmutable('2026-05-26 10:00:00');
        $expired = new DateTimeImmutable('2025-01-01 00:00:00');
        $future = new DateTimeImmutable('2027-01-01 00:00:00');

        yield 'expired include_until is inert' => [
            'reservedQty' => 5, 'unlimited' => false, 'min' => 5, 'max' => 1,
            'includeUntil' => $expired,
            'now' => $now,
            'masterQty' => 1, 'stockOnHand' => 0, 'reservedAlready' => 0, 'qtyInPurchase' => 0,
            'expected' => null,
        ];

        yield 'unlimited bypasses reserved floor' => [
            'reservedQty' => 100, 'unlimited' => true, 'min' => 0, 'max' => 0,
            'includeUntil' => $future,
            'now' => $now,
            'masterQty' => 1, 'stockOnHand' => 1, 'reservedAlready' => 0, 'qtyInPurchase' => 0,
            'expected' => null,
        ];

        yield 'reserved breached when stock would drop below reserved' => [
            'reservedQty' => 10, 'unlimited' => false, 'min' => 0, 'max' => 0,
            'includeUntil' => $future,
            'now' => $now,
            'masterQty' => 1, 'stockOnHand' => 12, 'reservedAlready' => 0, 'qtyInPurchase' => 3,
            'expected' => LinkViolation::RESERVED_QUANTITY_BREACHED,
        ];

        yield 'reserved satisfied when stock minus reserved minus purchase >= reservedQty' => [
            'reservedQty' => 5, 'unlimited' => false, 'min' => 0, 'max' => 0,
            'includeUntil' => $future,
            'now' => $now,
            'masterQty' => 1, 'stockOnHand' => 12, 'reservedAlready' => 0, 'qtyInPurchase' => 3,
            'expected' => null,
        ];

        yield 'minRequired not met when qty in purchase < min*masterQty' => [
            'reservedQty' => 0, 'unlimited' => true, 'min' => 2, 'max' => 0,
            'includeUntil' => $future,
            'now' => $now,
            'masterQty' => 3, 'stockOnHand' => 10, 'reservedAlready' => 0, 'qtyInPurchase' => 5,
            'expected' => LinkViolation::MIN_REQUIRED_NOT_MET,
        ];

        yield 'maxPerPurchase exceeded when qty in purchase > max' => [
            'reservedQty' => 0, 'unlimited' => true, 'min' => 0, 'max' => 3,
            'includeUntil' => $future,
            'now' => $now,
            'masterQty' => 1, 'stockOnHand' => 10, 'reservedAlready' => 0, 'qtyInPurchase' => 4,
            'expected' => LinkViolation::MAX_PER_PURCHASE_EXCEEDED,
        ];

        yield 'all constraints satisfied' => [
            'reservedQty' => 2, 'unlimited' => false, 'min' => 1, 'max' => 5,
            'includeUntil' => $future,
            'now' => $now,
            'masterQty' => 1, 'stockOnHand' => 10, 'reservedAlready' => 0, 'qtyInPurchase' => 2,
            'expected' => null,
        ];
    }

    #[Test]
    #[DataProvider('violationCases')]
    #[TestDox('wouldViolateAt() $_dataName.')]
    public function would_violate_at(
        int $reservedQty,
        bool $unlimited,
        int $min,
        int $max,
        ?DateTimeImmutable $includeUntil,
        DateTimeImmutable $now,
        int $masterQty,
        int $stockOnHand,
        int $reservedAlready,
        int $qtyInPurchase,
        ?LinkViolation $expected,
    ): void {
        $link = $this->makeLink(
            reservedQty: $reservedQty,
            unlimited: $unlimited,
            min: $min,
            max: $max,
            includeUntil: $includeUntil,
        );

        $actual = $link->wouldViolateAt(
            $now,
            Quantity::ofUnits($masterQty),
            Quantity::ofUnits($stockOnHand),
            Quantity::ofUnits($reservedAlready),
            Quantity::ofUnits($qtyInPurchase),
        );

        self::assertSame($expected, $actual);
    }

    private function makeLink(
        int $reservedQty,
        bool $unlimited,
        int $min,
        int $max,
        ?DateTimeImmutable $includeUntil,
    ): ItemLink {
        return ItemLink::link(
            ItemLinkId::fromString(self::LINK_ID),
            InventoryItemId::fromString(self::MASTER_ID),
            InventoryItemId::fromString(self::LINKED_ID),
            Quantity::ofUnits($reservedQty),
            $unlimited,
            Quantity::ofUnits($min),
            Quantity::ofUnits($max),
            $includeUntil,
            $this->clock,
        );
    }
}
