<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Query;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Application\Query\GetInventoryItemDetail;
use App\Inventory\Application\Query\GetInventoryItemDetailHandler;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryInventoryItems;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class GetInventoryItemDetailHandlerTest extends TestCase
{
    private const ITEM_ID = '019571bf-5d51-7000-b500-000000007001';
    private const LISTING_ID = '019571bf-5d51-7000-b500-000000007002';
    private const VENDOR_ID = '019571bf-5d51-7000-b500-000000007003';
    private const BATCH_MAIN_1 = '019571bf-5d51-7000-b500-000000007101';
    private const BATCH_MAIN_2 = '019571bf-5d51-7000-b500-000000007102';
    private const BATCH_LAKE_1 = '019571bf-5d51-7000-b500-000000007201';

    private InMemoryInventoryItems $items;
    private MockClock $clock;
    private GetInventoryItemDetailHandler $handler;

    protected function setUp(): void
    {
        $this->items = new InMemoryInventoryItems();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
        $this->handler = new GetInventoryItemDetailHandler($this->items);
    }

    #[Test]
    #[TestDox('Projects a registered InventoryItem with no stock into an empty per-facility block list.')]
    public function projects_empty_item(): void
    {
        $item = InventoryItem::register(
            InventoryItemId::fromString(self::ITEM_ID),
            ListingId::fromString(self::LISTING_ID),
            VendorId::fromString(self::VENDOR_ID),
            PosColor::ofHex('#A1B2C3'),
            trackInventory: true,
            rentable: false,
            reorderThreshold: ReorderThreshold::ofUnits(5),
            clock: $this->clock,
        );
        $item->releaseEvents();
        $this->items->add($item);

        $view = ($this->handler)(new GetInventoryItemDetail(self::ITEM_ID));

        self::assertSame(self::ITEM_ID, $view->inventoryItemId);
        self::assertSame(self::LISTING_ID, $view->listingId);
        self::assertSame(self::VENDOR_ID, $view->primaryVendorId);
        self::assertSame('#A1B2C3', $view->posColorHex);
        self::assertTrue($view->tracksInventory);
        self::assertFalse($view->rentable);
        self::assertSame(5, $view->reorderThresholdUnits);
        self::assertFalse($view->archived);
        self::assertSame(0, $view->totalOnHandUnits);
        self::assertSame([], $view->facilityStockBlocks);
    }

    #[Test]
    #[TestDox('Groups batches by facility in canonical order and lists them FIFO per facility.')]
    public function groups_batches_per_facility(): void
    {
        $item = InventoryItem::register(
            InventoryItemId::fromString(self::ITEM_ID),
            ListingId::fromString(self::LISTING_ID),
            null,
            PosColor::default(),
            true,
            false,
            ReorderThreshold::none(),
            $this->clock,
        );

        $main = FacilityCode::fromString('MAIN');
        $lake = FacilityCode::fromString('LAKESIDE');

        $item->receiveBatch(
            $main,
            Quantity::ofUnits(10),
            CostPerUnit::ofCents(100),
            null,
            null,
            StockBatchId::fromString(self::BATCH_MAIN_1),
            $this->clock,
        );
        $this->clock->modify('+1 minute');
        $item->receiveBatch(
            $main,
            Quantity::ofUnits(5),
            CostPerUnit::ofCents(200),
            null,
            null,
            StockBatchId::fromString(self::BATCH_MAIN_2),
            $this->clock,
        );
        $item->receiveBatch(
            $lake,
            Quantity::ofUnits(3),
            CostPerUnit::ofCents(300),
            null,
            null,
            StockBatchId::fromString(self::BATCH_LAKE_1),
            $this->clock,
        );
        $item->releaseEvents();
        $this->items->add($item);

        $view = ($this->handler)(new GetInventoryItemDetail(self::ITEM_ID));

        self::assertSame(18, $view->totalOnHandUnits);
        self::assertCount(2, $view->facilityStockBlocks);

        // ksort('LAKESIDE','MAIN') — LAKESIDE precedes MAIN alphabetically.
        $lakeBlock = $view->facilityStockBlocks[0];
        $mainBlock = $view->facilityStockBlocks[1];
        self::assertSame('LAKESIDE', $lakeBlock->facilityCode);
        self::assertSame(3, $lakeBlock->totalOnHandUnits);
        self::assertCount(1, $lakeBlock->batches);
        self::assertSame(self::BATCH_LAKE_1, $lakeBlock->batches[0]->stockBatchId);

        self::assertSame('MAIN', $mainBlock->facilityCode);
        self::assertSame(15, $mainBlock->totalOnHandUnits);
        self::assertCount(2, $mainBlock->batches);
        // FIFO: BATCH_MAIN_1 received first.
        self::assertSame(self::BATCH_MAIN_1, $mainBlock->batches[0]->stockBatchId);
        self::assertSame(100, $mainBlock->batches[0]->costPerUnitCents);
        self::assertSame(self::BATCH_MAIN_2, $mainBlock->batches[1]->stockBatchId);
        self::assertSame(200, $mainBlock->batches[1]->costPerUnitCents);
    }

    #[Test]
    #[TestDox('Unknown id throws InventoryItemNotFound from the underlying port.')]
    public function unknown_item_throws(): void
    {
        $this->expectException(InventoryItemNotFound::class);

        ($this->handler)(new GetInventoryItemDetail(self::ITEM_ID));
    }
}
