<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Event\StockMovementRecorded;
use App\Inventory\Domain\Event\StockReceived;
use App\Inventory\Domain\Event\StockReturned;
use App\Inventory\Domain\Exception\InsufficientStock;
use App\Inventory\Domain\Exception\InvalidStockBatchQuantity;
use App\Inventory\Domain\Exception\InvalidStockReturn;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\ValueObject\Comment;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
#[Group('domain-fifo')]
final class InventoryItemFifoTest extends TestCase
{
    private const ITEM_ID = '019571bf-5d51-7000-b500-000000000200';
    private const LISTING_ID = '019571bf-5d51-7000-b500-000000000201';
    private const BATCH_1 = '019571bf-5d51-7000-b500-000000000301';
    private const BATCH_2 = '019571bf-5d51-7000-b500-000000000302';
    private const BATCH_3 = '019571bf-5d51-7000-b500-000000000303';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-25 10:00:00'));
    }

    #[Test]
    #[TestDox('receiveBatch() appends a batch and records StockReceived with the new id.')]
    public function receive_batch_records_event(): void
    {
        $item = $this->registerItem();
        $item->releaseEvents();

        $id = $item->receiveBatch(
            Quantity::ofUnits(10),
            CostPerUnit::ofCents(250),
            null,
            null,
            StockBatchId::fromString(self::BATCH_1),
            $this->clock,
        );

        self::assertSame(self::BATCH_1, $id->value);
        self::assertSame(10, $item->totalOnHand()->units);

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(StockReceived::class, $events[0]);
        self::assertSame(self::BATCH_1, $events[0]->stockBatchId->value);
        self::assertSame(10, $events[0]->quantity->units);
    }

    #[Test]
    #[TestDox('receiveBatch() with zero quantity throws InvalidStockBatchQuantity.')]
    public function receive_batch_rejects_zero_quantity(): void
    {
        $item = $this->registerItem();

        $this->expectException(InvalidStockBatchQuantity::class);

        $item->receiveBatch(
            Quantity::zero(),
            CostPerUnit::ofCents(100),
            null,
            null,
            StockBatchId::fromString(self::BATCH_1),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('Single-batch consume emits exactly one StockMovementRecorded and decrements the batch.')]
    public function single_batch_consume(): void
    {
        $item = $this->registerItem();
        $this->receiveBatch($item, self::BATCH_1, units: 10, costCents: 250);
        $item->releaseEvents();

        $item->consume(Quantity::ofUnits(3), StockMovementReason::SALE, $this->clock);

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(StockMovementRecorded::class, $events[0]);
        self::assertSame(self::BATCH_1, $events[0]->stockBatchId->value);
        self::assertSame(3, $events[0]->quantityConsumed->units);
        self::assertSame(250, $events[0]->costPerUnit->cents);
        self::assertSame(StockMovementReason::SALE, $events[0]->reason);
        self::assertSame(7, $item->totalOnHand()->units);
    }

    #[Test]
    #[TestDox('Multi-batch consume walks oldest first and emits one event per batch touched.')]
    public function multi_batch_consume_spans_two_batches(): void
    {
        $item = $this->registerItem();
        $this->receiveBatch($item, self::BATCH_1, units: 4, costCents: 100);
        $this->advanceClock();
        $this->receiveBatch($item, self::BATCH_2, units: 10, costCents: 200);
        $item->releaseEvents();

        $item->consume(Quantity::ofUnits(7), StockMovementReason::RENTAL_CHECKOUT, $this->clock);

        $events = $item->releaseEvents();
        self::assertCount(2, $events);

        $first = $events[0];
        $second = $events[1];
        self::assertInstanceOf(StockMovementRecorded::class, $first);
        self::assertInstanceOf(StockMovementRecorded::class, $second);

        self::assertSame(self::BATCH_1, $first->stockBatchId->value);
        self::assertSame(4, $first->quantityConsumed->units);
        self::assertSame(100, $first->costPerUnit->cents);

        self::assertSame(self::BATCH_2, $second->stockBatchId->value);
        self::assertSame(3, $second->quantityConsumed->units);
        self::assertSame(200, $second->costPerUnit->cents);

        self::assertSame(7, $item->totalOnHand()->units);
    }

    #[Test]
    #[TestDox('Exact-fit consume empties the last batch precisely and emits one event.')]
    public function exact_fit_consume_empties_last_batch(): void
    {
        $item = $this->registerItem();
        $this->receiveBatch($item, self::BATCH_1, units: 5, costCents: 100);
        $item->releaseEvents();

        $item->consume(Quantity::ofUnits(5), StockMovementReason::SALE, $this->clock);

        self::assertSame(0, $item->totalOnHand()->units);
        self::assertCount(1, $item->releaseEvents());
    }

    #[Test]
    #[TestDox('Insufficient stock throws InsufficientStock atomically and mutates no batches.')]
    public function insufficient_stock_is_atomic(): void
    {
        $item = $this->registerItem();
        $this->receiveBatch($item, self::BATCH_1, units: 4, costCents: 100);
        $this->advanceClock();
        $this->receiveBatch($item, self::BATCH_2, units: 2, costCents: 200);
        $item->releaseEvents();

        try {
            $item->consume(Quantity::ofUnits(10), StockMovementReason::SALE, $this->clock);
            self::fail('Expected InsufficientStock to be thrown.');
        } catch (InsufficientStock $e) {
            self::assertSame(10, $e->requested->units);
            self::assertSame(6, $e->available->units);
            self::assertSame(self::ITEM_ID, $e->inventoryItemId->value);
        }

        self::assertSame(6, $item->totalOnHand()->units, 'no batches should have mutated');
        self::assertSame([], $item->releaseEvents(), 'no StockMovementRecorded events on failure');
    }

    #[Test]
    #[TestDox('FIFO tiebreaker on same receivedAt uses StockBatchId string comparison.')]
    public function fifo_tiebreaker_on_same_received_at(): void
    {
        $item = $this->registerItem();
        // Both batches received at the same clock tick — id is the tiebreaker.
        $this->receiveBatch($item, self::BATCH_2, units: 3, costCents: 200);
        $this->receiveBatch($item, self::BATCH_1, units: 3, costCents: 100);
        $item->releaseEvents();

        $item->consume(Quantity::ofUnits(4), StockMovementReason::SALE, $this->clock);

        $events = $item->releaseEvents();
        self::assertCount(2, $events);
        $first = $events[0];
        $second = $events[1];
        self::assertInstanceOf(StockMovementRecorded::class, $first);
        self::assertInstanceOf(StockMovementRecorded::class, $second);
        // BATCH_1 (lexicographically earlier id) must consume first.
        self::assertSame(self::BATCH_1, $first->stockBatchId->value);
        self::assertSame(3, $first->quantityConsumed->units);
        self::assertSame(self::BATCH_2, $second->stockBatchId->value);
        self::assertSame(1, $second->quantityConsumed->units);
    }

    #[Test]
    #[TestDox('returnUnits() restores units LIFO on the most-recently-consumed batch.')]
    public function return_units_restores_lifo(): void
    {
        $item = $this->registerItem();
        $this->receiveBatch($item, self::BATCH_1, units: 5, costCents: 100);
        $this->advanceClock();
        $this->receiveBatch($item, self::BATCH_2, units: 5, costCents: 200);
        $item->consume(Quantity::ofUnits(7), StockMovementReason::SALE, $this->clock);
        $item->releaseEvents();

        $item->returnUnits(Quantity::ofUnits(3), $this->clock);

        // After consume(7): BATCH_1 fully consumed (5), BATCH_2 partially (2 of 5).
        // Returning 3 LIFO restores up to consumed-quantity per batch: BATCH_2 can
        // absorb only its 2 consumed units, the remaining 1 falls back to BATCH_1.
        $events = $item->releaseEvents();
        self::assertCount(2, $events);
        $first = $events[0];
        $second = $events[1];
        self::assertInstanceOf(StockReturned::class, $first);
        self::assertInstanceOf(StockReturned::class, $second);
        self::assertSame(self::BATCH_2, $first->stockBatchId->value);
        self::assertSame(2, $first->quantityRestored->units);
        self::assertSame(200, $first->costPerUnit->cents);
        self::assertSame(self::BATCH_1, $second->stockBatchId->value);
        self::assertSame(1, $second->quantityRestored->units);
        self::assertSame(100, $second->costPerUnit->cents);
        self::assertSame(6, $item->totalOnHand()->units);
    }

    #[Test]
    #[TestDox('returnUnits() walks LIFO across multiple consumed batches when needed.')]
    public function return_units_spans_multiple_batches(): void
    {
        $item = $this->registerItem();
        $this->receiveBatch($item, self::BATCH_1, units: 5, costCents: 100);
        $this->advanceClock();
        $this->receiveBatch($item, self::BATCH_2, units: 5, costCents: 200);
        $item->consume(Quantity::ofUnits(8), StockMovementReason::SALE, $this->clock);
        $item->releaseEvents();

        $item->returnUnits(Quantity::ofUnits(7), $this->clock);

        // After consume(8): BATCH_1 fully consumed (5), BATCH_2 partial (3 of 5).
        // Returning 7 LIFO restores 3 to BATCH_2 (its consumed cap) and 4 to BATCH_1.
        $events = $item->releaseEvents();
        self::assertCount(2, $events);
        $first = $events[0];
        $second = $events[1];
        self::assertInstanceOf(StockReturned::class, $first);
        self::assertInstanceOf(StockReturned::class, $second);
        self::assertSame(self::BATCH_2, $first->stockBatchId->value);
        self::assertSame(3, $first->quantityRestored->units);
        self::assertSame(self::BATCH_1, $second->stockBatchId->value);
        self::assertSame(4, $second->quantityRestored->units);
        self::assertSame(9, $item->totalOnHand()->units);
    }

    #[Test]
    #[TestDox('Returning more than ever-consumed throws InvalidStockReturn.')]
    public function return_exceeding_consumed_throws(): void
    {
        $item = $this->registerItem();
        $this->receiveBatch($item, self::BATCH_1, units: 5, costCents: 100);
        $item->consume(Quantity::ofUnits(2), StockMovementReason::SALE, $this->clock);
        $item->releaseEvents();

        try {
            $item->returnUnits(Quantity::ofUnits(3), $this->clock);
            self::fail('Expected InvalidStockReturn.');
        } catch (InvalidStockReturn $e) {
            self::assertSame(3, $e->requested->units);
            self::assertSame(2, $e->restorable->units);
        }

        self::assertSame(3, $item->totalOnHand()->units, 'no batches mutated on failure');
        self::assertSame([], $item->releaseEvents());
    }

    #[Test]
    #[TestDox('consume() with zero quantity is a no-op (records nothing, mutates nothing).')]
    public function consume_zero_is_noop(): void
    {
        $item = $this->registerItem();
        $this->receiveBatch($item, self::BATCH_1, units: 5, costCents: 100);
        $item->releaseEvents();

        $item->consume(Quantity::zero(), StockMovementReason::SALE, $this->clock);

        self::assertSame([], $item->releaseEvents());
        self::assertSame(5, $item->totalOnHand()->units);
    }

    #[Test]
    #[TestDox('returnUnits() with zero quantity is a no-op.')]
    public function return_zero_is_noop(): void
    {
        $item = $this->registerItem();
        $this->receiveBatch($item, self::BATCH_1, units: 5, costCents: 100);
        $item->consume(Quantity::ofUnits(2), StockMovementReason::SALE, $this->clock);
        $item->releaseEvents();

        $item->returnUnits(Quantity::zero(), $this->clock);

        self::assertSame([], $item->releaseEvents());
        self::assertSame(3, $item->totalOnHand()->units);
    }

    #[Test]
    #[TestDox('receiveBatch() persists PO line reference and comment when provided.')]
    public function receive_batch_carries_po_line_and_comment(): void
    {
        $item = $this->registerItem();
        $item->releaseEvents();

        $poLineId = PurchaseOrderLineId::fromString(self::BATCH_3);
        $comment = Comment::of('Restock from main supplier');

        $item->receiveBatch(
            Quantity::ofUnits(2),
            CostPerUnit::ofCents(500),
            $poLineId,
            $comment,
            StockBatchId::fromString(self::BATCH_1),
            $this->clock,
        );

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(StockReceived::class, $events[0]);
        self::assertNotNull($events[0]->sourceLineId);
        self::assertSame(self::BATCH_3, $events[0]->sourceLineId->value);
        self::assertNotNull($events[0]->comments);
        self::assertSame('Restock from main supplier', $events[0]->comments->value);
    }

    private function registerItem(): InventoryItem
    {
        return InventoryItem::register(
            InventoryItemId::fromString(self::ITEM_ID),
            ListingId::fromString(self::LISTING_ID),
            null,
            PosColor::default(),
            true,
            false,
            ReorderThreshold::none(),
            $this->clock,
        );
    }

    private function receiveBatch(InventoryItem $item, string $batchId, int $units, int $costCents): void
    {
        $item->receiveBatch(
            Quantity::ofUnits($units),
            CostPerUnit::ofCents($costCents),
            null,
            null,
            StockBatchId::fromString($batchId),
            $this->clock,
        );
    }

    private function advanceClock(): void
    {
        $this->clock->modify('+1 minute');
    }
}
