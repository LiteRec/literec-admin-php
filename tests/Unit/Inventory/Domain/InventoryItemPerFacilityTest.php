<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Event\StockMovementRecorded;
use App\Inventory\Domain\Event\StockTransferredIn;
use App\Inventory\Domain\Event\StockTransferredOut;
use App\Inventory\Domain\Exception\CannotTransferToSameFacility;
use App\Inventory\Domain\Exception\InsufficientStock;
use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementId;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use App\Inventory\Domain\ValueObject\VendorId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
#[Group('domain-fifo')]
final class InventoryItemPerFacilityTest extends TestCase
{
    private const ITEM_ID = '019571bf-5d51-7000-b500-000000000400';
    private const LISTING_ID = '019571bf-5d51-7000-b500-000000000401';
    private const BATCH_A1 = '019571bf-5d51-7000-b500-000000000501';
    private const BATCH_A2 = '019571bf-5d51-7000-b500-000000000502';
    private const BATCH_B1 = '019571bf-5d51-7000-b500-000000000511';
    private const NEW_BATCH_PREFIX = '019571bf-5d51-7000-b500-00000000060';

    private MockClock $clock;
    private FacilityCode $main;
    private FacilityCode $lakeside;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-25 10:00:00'));
        $this->main = FacilityCode::fromString('MAIN');
        $this->lakeside = FacilityCode::fromString('LAKESIDE');
    }

    #[Test]
    #[TestDox('Per-facility receive: same item shows distinct totalOnHandAt() at two facilities.')]
    public function per_facility_receive_keeps_totals_distinct(): void
    {
        $item = $this->registerItem();
        $this->receiveAt($item, $this->main, self::BATCH_A1, 10, 100);
        $this->receiveAt($item, $this->lakeside, self::BATCH_B1, 3, 100);

        self::assertSame(10, $item->totalOnHandAt($this->main)->units);
        self::assertSame(3, $item->totalOnHandAt($this->lakeside)->units);
        self::assertSame(13, $item->totalOnHand()->units);
    }

    #[Test]
    #[TestDox('consume(A, q) does not touch any batch at facility B.')]
    public function consume_at_one_facility_does_not_affect_other(): void
    {
        $item = $this->registerItem();
        $this->receiveAt($item, $this->main, self::BATCH_A1, 10, 100);
        $this->receiveAt($item, $this->lakeside, self::BATCH_B1, 5, 200);
        $item->releaseEvents();

        $item->consume($this->main, Quantity::ofUnits(4), StockMovementReason::SALE, $this->clock);

        self::assertSame(6, $item->totalOnHandAt($this->main)->units);
        self::assertSame(5, $item->totalOnHandAt($this->lakeside)->units, 'lakeside untouched');

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(StockMovementRecorded::class, $event);
        self::assertSame(self::BATCH_A1, $event->stockBatchId->value);
    }

    #[Test]
    #[TestDox('consume() at a facility with no batches throws InsufficientStock with zero available.')]
    public function consume_at_empty_facility_throws(): void
    {
        $item = $this->registerItem();
        $this->receiveAt($item, $this->main, self::BATCH_A1, 10, 100);
        $item->releaseEvents();

        try {
            $item->consume($this->lakeside, Quantity::ofUnits(1), StockMovementReason::SALE, $this->clock);
            self::fail('Expected InsufficientStock.');
        } catch (InsufficientStock $e) {
            self::assertSame(1, $e->requested->units);
            self::assertSame(0, $e->available->units);
        }

        self::assertSame(10, $item->totalOnHandAt($this->main)->units);
        self::assertSame([], $item->releaseEvents());
    }

    #[Test]
    #[TestDox('transferStock() from a single source batch creates one destination batch with preserved cost.')]
    public function transfer_full_single_batch_preserves_cost(): void
    {
        $item = $this->registerItem();
        $this->receiveAt($item, $this->main, self::BATCH_A1, 10, 400);
        $item->releaseEvents();

        $idGen = $this->sequentialIdentityGenerator();

        $this->advanceClock();
        $item->transferStock($this->main, $this->lakeside, Quantity::ofUnits(10), $this->clock, $idGen);

        self::assertSame(0, $item->totalOnHandAt($this->main)->units);
        self::assertSame(10, $item->totalOnHandAt($this->lakeside)->units);
        self::assertSame(10, $item->totalOnHand()->units, 'cross-facility total preserved');

        $events = $item->releaseEvents();
        // Expect 4 events: SMR(OUT), SMR(IN), StockTransferredOut, StockTransferredIn.
        self::assertCount(4, $events);

        $out = $events[0];
        $in = $events[1];
        $txOut = $events[2];
        $txIn = $events[3];
        self::assertInstanceOf(StockMovementRecorded::class, $out);
        self::assertSame(StockMovementReason::TRANSFER_OUT, $out->reason);
        self::assertSame(self::BATCH_A1, $out->stockBatchId->value);
        self::assertSame(400, $out->costPerUnit->cents);

        self::assertInstanceOf(StockMovementRecorded::class, $in);
        self::assertSame(StockMovementReason::TRANSFER_IN, $in->reason);
        self::assertSame(400, $in->costPerUnit->cents, 'destination preserves source cost');

        self::assertInstanceOf(StockTransferredOut::class, $txOut);
        self::assertCount(1, $txOut->lineItems);
        self::assertSame(self::BATCH_A1, $txOut->lineItems[0]->stockBatchId->value);
        self::assertSame(10, $txOut->lineItems[0]->quantity->units);

        self::assertInstanceOf(StockTransferredIn::class, $txIn);
        self::assertCount(1, $txIn->lineItems);
        self::assertSame(10, $txIn->lineItems[0]->quantity->units);
        self::assertSame(400, $txIn->lineItems[0]->costPerUnit->cents);
    }

    #[Test]
    #[TestDox('Partial-batch transfer leaves remainder at source with original cost.')]
    public function transfer_partial_batch(): void
    {
        $item = $this->registerItem();
        $this->receiveAt($item, $this->main, self::BATCH_A1, 10, 400);
        $item->releaseEvents();

        $idGen = $this->sequentialIdentityGenerator();
        $item->transferStock($this->main, $this->lakeside, Quantity::ofUnits(3), $this->clock, $idGen);

        self::assertSame(7, $item->totalOnHandAt($this->main)->units);
        self::assertSame(3, $item->totalOnHandAt($this->lakeside)->units);
    }

    #[Test]
    #[TestDox('Multi-batch transfer with mixed costs produces distinct destination batches per source slice.')]
    public function transfer_spans_multiple_source_batches(): void
    {
        $item = $this->registerItem();
        $this->receiveAt($item, $this->main, self::BATCH_A1, 10, 400);
        $this->advanceClock();
        $this->receiveAt($item, $this->main, self::BATCH_A2, 5, 450);
        $item->releaseEvents();

        $idGen = $this->sequentialIdentityGenerator();
        $item->transferStock($this->main, $this->lakeside, Quantity::ofUnits(15), $this->clock, $idGen);

        self::assertSame(0, $item->totalOnHandAt($this->main)->units);
        self::assertSame(15, $item->totalOnHandAt($this->lakeside)->units);

        $events = $item->releaseEvents();
        self::assertNotEmpty($events);
        $txIn = end($events);
        self::assertInstanceOf(StockTransferredIn::class, $txIn);
        self::assertCount(2, $txIn->lineItems, 'two destination batches preserve mixed costs');
        self::assertSame(10, $txIn->lineItems[0]->quantity->units);
        self::assertSame(400, $txIn->lineItems[0]->costPerUnit->cents);
        self::assertSame(5, $txIn->lineItems[1]->quantity->units);
        self::assertSame(450, $txIn->lineItems[1]->costPerUnit->cents);
    }

    #[Test]
    #[TestDox('Cost basis is preserved: sum(remaining * cost) is invariant across the transfer.')]
    public function transfer_preserves_total_cost_basis(): void
    {
        $item = $this->registerItem();
        $this->receiveAt($item, $this->main, self::BATCH_A1, 10, 400);
        $this->advanceClock();
        $this->receiveAt($item, $this->main, self::BATCH_A2, 5, 450);

        $before = $this->totalCostBasis($item);

        $idGen = $this->sequentialIdentityGenerator();
        $item->transferStock($this->main, $this->lakeside, Quantity::ofUnits(12), $this->clock, $idGen);

        self::assertSame($before, $this->totalCostBasis($item));
    }

    #[Test]
    #[TestDox('Insufficient source stock throws InsufficientStock atomically; no source or destination mutation.')]
    public function transfer_insufficient_source_is_atomic(): void
    {
        $item = $this->registerItem();
        $this->receiveAt($item, $this->main, self::BATCH_A1, 4, 100);
        $item->releaseEvents();

        $idGen = $this->sequentialIdentityGenerator();

        try {
            $item->transferStock($this->main, $this->lakeside, Quantity::ofUnits(10), $this->clock, $idGen);
            self::fail('Expected InsufficientStock.');
        } catch (InsufficientStock $e) {
            self::assertSame(10, $e->requested->units);
            self::assertSame(4, $e->available->units);
        }

        self::assertSame(4, $item->totalOnHandAt($this->main)->units, 'source untouched');
        self::assertSame(0, $item->totalOnHandAt($this->lakeside)->units, 'destination untouched');
        self::assertSame([], $item->releaseEvents());
    }

    #[Test]
    #[TestDox('Transferred-in batches sit AFTER pre-existing destination batches in FIFO order.')]
    public function transfer_orders_new_destination_batches_after_existing(): void
    {
        $item = $this->registerItem();
        $this->receiveAt($item, $this->main, self::BATCH_A1, 2, 400);
        $this->receiveAt($item, $this->lakeside, self::BATCH_B1, 1, 300);
        $item->releaseEvents();

        $this->advanceClock();
        $item->transferStock(
            $this->main,
            $this->lakeside,
            Quantity::ofUnits(1),
            $this->clock,
            $this->sequentialIdentityGenerator(),
        );
        $item->releaseEvents();

        // Consume one unit at lakeside — should drain BATCH_B1 first (oldest).
        $item->consume($this->lakeside, Quantity::ofUnits(1), StockMovementReason::SALE, $this->clock);

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(StockMovementRecorded::class, $event);
        self::assertSame(self::BATCH_B1, $event->stockBatchId->value, 'pre-existing batch must consume first');
        self::assertSame(300, $event->costPerUnit->cents);
    }

    #[Test]
    #[TestDox('Transfer from a facility to itself throws CannotTransferToSameFacility.')]
    public function transfer_to_same_facility_throws(): void
    {
        $item = $this->registerItem();
        $this->receiveAt($item, $this->main, self::BATCH_A1, 5, 100);
        $item->releaseEvents();

        $this->expectException(CannotTransferToSameFacility::class);

        $item->transferStock(
            $this->main,
            $this->main,
            Quantity::ofUnits(1),
            $this->clock,
            $this->sequentialIdentityGenerator(),
        );
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

    private function receiveAt(
        InventoryItem $item,
        FacilityCode $facility,
        string $batchId,
        int $units,
        int $costCents,
    ): void {
        $item->receiveBatch(
            $facility,
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

    private function totalCostBasis(InventoryItem $item): int
    {
        $sum = 0;
        foreach ($item->batches() as $batch) {
            $sum += $batch->remainingQuantity()->units * $batch->costPerUnit()->cents;
        }
        return $sum;
    }

    private function sequentialIdentityGenerator(): IdentityGenerator
    {
        return new class (self::NEW_BATCH_PREFIX) implements IdentityGenerator {
            private int $sequence = 0;

            public function __construct(private readonly string $prefix)
            {
            }

            public function nextInventoryItemId(): InventoryItemId
            {
                throw new \LogicException('not used');
            }

            public function nextStockBatchId(): StockBatchId
            {
                $this->sequence++;
                return StockBatchId::fromString(sprintf('%s%d', $this->prefix, $this->sequence));
            }

            public function nextStockMovementId(): StockMovementId
            {
                throw new \LogicException('not used');
            }

            public function nextVendorId(): VendorId
            {
                throw new \LogicException('not used');
            }
        };
    }
}
