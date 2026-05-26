<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Event\InventoryItemArchived;
use App\Inventory\Domain\Event\InventoryItemPosColorUpdated;
use App\Inventory\Domain\Event\InventoryItemPrimaryVendorUpdated;
use App\Inventory\Domain\Event\InventoryItemRegistered;
use App\Inventory\Domain\Event\InventoryItemRentableChanged;
use App\Inventory\Domain\Event\InventoryItemReorderThresholdUpdated;
use App\Inventory\Domain\Event\InventoryItemTrackingChanged;
use App\Inventory\Domain\Exception\InventoryItemIsArchived;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use App\Inventory\Domain\ValueObject\VendorId;
use Closure;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

#[Small]
final class InventoryItemTest extends TestCase
{
    private const ITEM_ID = '019571bf-5d51-7000-b500-000000000100';
    private const LISTING_ID = '019571bf-5d51-7000-b500-000000000101';
    private const VENDOR_ID = '019571bf-5d51-7000-b500-000000000102';
    private const OTHER_VENDOR_ID = '019571bf-5d51-7000-b500-000000000103';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-25 12:00:00'));
    }

    #[Test]
    #[TestDox('::register() records InventoryItemRegistered with the full initial state and the clock instant.')]
    public function register_records_inventory_item_registered_with_max_parameters(): void
    {
        $item = $this->registerWithMaxParameters();

        $events = $item->releaseEvents();
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf(InventoryItemRegistered::class, $event);
        self::assertSame(self::ITEM_ID, $event->inventoryItemId->value);
        self::assertSame(self::LISTING_ID, $event->listingId->value);
        self::assertNotNull($event->primaryVendorId);
        self::assertSame(self::VENDOR_ID, $event->primaryVendorId->value);
        self::assertSame('#FF00AA', $event->posColor->hex);
        self::assertTrue($event->trackInventory);
        self::assertTrue($event->rentable);
        self::assertSame(5, $event->reorderThreshold->units);
        self::assertEquals($this->clock->now(), $event->occurredAt);

        self::assertSame(self::ITEM_ID, $item->id()->value);
        self::assertSame(self::LISTING_ID, $item->listingId()->value);
        self::assertNotNull($item->primaryVendorId());
        self::assertSame(self::VENDOR_ID, $item->primaryVendorId()->value);
        self::assertTrue($item->tracksInventory());
        self::assertTrue($item->isRentable());
        self::assertFalse($item->isArchived());
        self::assertEquals($this->clock->now(), $item->registeredAt());
        self::assertEquals($this->clock->now(), $item->updatedAt());
    }

    #[Test]
    #[TestDox('::register() records InventoryItemRegistered with min params (no vendor, no threshold).')]
    public function register_records_with_min_parameters(): void
    {
        $item = $this->registerWithMinParameters();

        $events = $item->releaseEvents();
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf(InventoryItemRegistered::class, $event);
        self::assertNull($event->primaryVendorId);
        self::assertSame('#FFFFFF', $event->posColor->hex);
        self::assertFalse($event->trackInventory);
        self::assertFalse($event->rentable);
        self::assertNull($event->reorderThreshold->units);
        self::assertNull($item->primaryVendorId());
    }

    #[Test]
    #[TestDox('::releaseEvents() returns the buffer and clears it.')]
    public function release_events_clears_the_buffer(): void
    {
        $item = $this->registerWithMaxParameters();

        self::assertCount(1, $item->releaseEvents());
        self::assertSame([], $item->releaseEvents());
    }

    #[Test]
    #[TestDox('::enableTracking() records InventoryItemTrackingChanged(true) when tracking was off.')]
    public function enable_tracking_records_event(): void
    {
        $item = $this->registerWithMinParameters();
        $item->releaseEvents();

        $this->clock->modify('+1 hour');
        $item->enableTracking($this->clock);

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InventoryItemTrackingChanged::class, $events[0]);
        self::assertTrue($events[0]->trackInventory);
        self::assertTrue($item->tracksInventory());
        self::assertEquals($this->clock->now(), $item->updatedAt());
    }

    #[Test]
    #[TestDox('::enableTracking() is a no-op when tracking is already on.')]
    public function enable_tracking_is_idempotent(): void
    {
        $item = $this->registerWithMaxParameters();
        $item->releaseEvents();

        $item->enableTracking($this->clock);

        self::assertSame([], $item->releaseEvents());
    }

    #[Test]
    #[TestDox('::disableTracking() records InventoryItemTrackingChanged(false) when tracking was on.')]
    public function disable_tracking_records_event(): void
    {
        $item = $this->registerWithMaxParameters();
        $item->releaseEvents();

        $item->disableTracking($this->clock);

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InventoryItemTrackingChanged::class, $events[0]);
        self::assertFalse($events[0]->trackInventory);
        self::assertFalse($item->tracksInventory());
    }

    #[Test]
    #[TestDox('::markRentable() records InventoryItemRentableChanged(true) when previously not rentable.')]
    public function mark_rentable_records_event(): void
    {
        $item = $this->registerWithMinParameters();
        $item->releaseEvents();

        $item->markRentable($this->clock);

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InventoryItemRentableChanged::class, $events[0]);
        self::assertTrue($events[0]->rentable);
        self::assertTrue($item->isRentable());
    }

    #[Test]
    #[TestDox('::markNonRentable() records InventoryItemRentableChanged(false) when previously rentable.')]
    public function mark_non_rentable_records_event(): void
    {
        $item = $this->registerWithMaxParameters();
        $item->releaseEvents();

        $item->markNonRentable($this->clock);

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InventoryItemRentableChanged::class, $events[0]);
        self::assertFalse($events[0]->rentable);
        self::assertFalse($item->isRentable());
    }

    #[Test]
    #[TestDox('::markRentable() is a no-op when already rentable.')]
    public function mark_rentable_is_idempotent(): void
    {
        $item = $this->registerWithMaxParameters();
        $item->releaseEvents();

        $item->markRentable($this->clock);

        self::assertSame([], $item->releaseEvents());
    }

    #[Test]
    #[TestDox('::updatePosColor() records InventoryItemPosColorUpdated when the color changes.')]
    public function update_pos_color_records_event(): void
    {
        $item = $this->registerWithMaxParameters();
        $item->releaseEvents();

        $item->updatePosColor(PosColor::ofHex('#112233'), $this->clock);

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InventoryItemPosColorUpdated::class, $events[0]);
        self::assertSame('#112233', $events[0]->posColor->hex);
        self::assertSame('#112233', $item->posColor()->hex);
    }

    #[Test]
    #[TestDox('::updatePosColor() is a no-op when the value equals the current color (case-insensitive).')]
    public function update_pos_color_is_idempotent(): void
    {
        $item = $this->registerWithMaxParameters();
        $item->releaseEvents();

        $item->updatePosColor(PosColor::ofHex('#ff00aa'), $this->clock);

        self::assertSame([], $item->releaseEvents());
    }

    #[Test]
    #[TestDox('::updatePrimaryVendor() records InventoryItemPrimaryVendorUpdated when the vendor changes (or clears).')]
    public function update_primary_vendor_records_event(): void
    {
        $item = $this->registerWithMaxParameters();
        $item->releaseEvents();

        $item->updatePrimaryVendor(VendorId::fromString(self::OTHER_VENDOR_ID), $this->clock);

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InventoryItemPrimaryVendorUpdated::class, $events[0]);
        self::assertNotNull($events[0]->primaryVendorId);
        self::assertSame(self::OTHER_VENDOR_ID, $events[0]->primaryVendorId->value);

        $item->updatePrimaryVendor(null, $this->clock);
        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InventoryItemPrimaryVendorUpdated::class, $events[0]);
        self::assertNull($events[0]->primaryVendorId);
        self::assertNull($item->primaryVendorId());
    }

    #[Test]
    #[TestDox('::updatePrimaryVendor() is a no-op when the new value equals the current vendor.')]
    public function update_primary_vendor_is_idempotent(): void
    {
        $item = $this->registerWithMaxParameters();
        $item->releaseEvents();

        $item->updatePrimaryVendor(VendorId::fromString(self::VENDOR_ID), $this->clock);

        self::assertSame([], $item->releaseEvents());
    }

    #[Test]
    #[TestDox('::updatePrimaryVendor() is a no-op when both current and new values are null.')]
    public function update_primary_vendor_null_to_null_is_noop(): void
    {
        $item = $this->registerWithMinParameters();
        $item->releaseEvents();

        $item->updatePrimaryVendor(null, $this->clock);

        self::assertSame([], $item->releaseEvents());
    }

    #[Test]
    #[TestDox('::updateReorderThreshold() records InventoryItemReorderThresholdUpdated when the threshold changes.')]
    public function update_reorder_threshold_records_event(): void
    {
        $item = $this->registerWithMaxParameters();
        $item->releaseEvents();

        $item->updateReorderThreshold(ReorderThreshold::ofUnits(10), $this->clock);

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InventoryItemReorderThresholdUpdated::class, $events[0]);
        self::assertSame(10, $events[0]->reorderThreshold->units);
        self::assertSame(10, $item->reorderThreshold()->units);
    }

    #[Test]
    #[TestDox('::updateReorderThreshold() is a no-op when the value equals the current threshold.')]
    public function update_reorder_threshold_is_idempotent(): void
    {
        $item = $this->registerWithMaxParameters();
        $item->releaseEvents();

        $item->updateReorderThreshold(ReorderThreshold::ofUnits(5), $this->clock);

        self::assertSame([], $item->releaseEvents());
    }

    #[Test]
    #[TestDox('::archive() records InventoryItemArchived and flips the archived flag.')]
    public function archive_records_event(): void
    {
        $item = $this->registerWithMaxParameters();
        $item->releaseEvents();

        $item->archive($this->clock);

        $events = $item->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InventoryItemArchived::class, $events[0]);
        self::assertTrue($item->isArchived());
    }

    #[Test]
    #[TestDox('::archive() is idempotent: a second call records nothing and leaves the item archived.')]
    public function archive_is_idempotent(): void
    {
        $item = $this->registerWithMaxParameters();
        $item->releaseEvents();

        $item->archive($this->clock);
        $item->releaseEvents();
        $item->archive($this->clock);

        self::assertSame([], $item->releaseEvents());
        self::assertTrue($item->isArchived());
    }

    /**
     * @return Generator<string, array{Closure(InventoryItem, ClockInterface): void}>
     */
    public static function mutators(): Generator
    {
        yield 'enableTracking' => [
            static fn(InventoryItem $i, ClockInterface $c) => $i->enableTracking($c),
        ];
        yield 'disableTracking' => [
            static fn(InventoryItem $i, ClockInterface $c) => $i->disableTracking($c),
        ];
        yield 'markRentable' => [
            static fn(InventoryItem $i, ClockInterface $c) => $i->markRentable($c),
        ];
        yield 'markNonRentable' => [
            static fn(InventoryItem $i, ClockInterface $c) => $i->markNonRentable($c),
        ];
        yield 'updatePosColor' => [
            static fn(InventoryItem $i, ClockInterface $c) => $i->updatePosColor(PosColor::ofHex('#000000'), $c),
        ];
        yield 'updatePrimaryVendor' => [
            static fn(InventoryItem $i, ClockInterface $c) => $i->updatePrimaryVendor(null, $c),
        ];
        yield 'updateReorderThreshold' => [
            static fn(InventoryItem $i, ClockInterface $c) => $i->updateReorderThreshold(ReorderThreshold::none(), $c),
        ];
        $facility = FacilityCode::fromString('MAIN');
        yield 'receiveBatch' => [
            static function (InventoryItem $i, ClockInterface $c) use ($facility): void {
                $i->receiveBatch(
                    $facility,
                    Quantity::ofUnits(1),
                    CostPerUnit::zero(),
                    null,
                    null,
                    StockBatchId::fromString('019571bf-5d51-7000-b500-0000000003ff'),
                    $c,
                );
            },
        ];
        yield 'consume' => [
            static fn(InventoryItem $i, ClockInterface $c) => $i->consume(
                $facility,
                Quantity::ofUnits(1),
                StockMovementReason::SALE,
                $c,
            ),
        ];
        yield 'returnUnits' => [
            static fn(InventoryItem $i, ClockInterface $c) => $i->returnUnits(
                $facility,
                Quantity::ofUnits(1),
                $c,
            ),
        ];
        yield 'transferStock' => [
            static fn(InventoryItem $i, ClockInterface $c) => $i->transferStock(
                $facility,
                FacilityCode::fromString('OTHER'),
                Quantity::ofUnits(1),
                $c,
                new class implements \App\Inventory\Domain\IdentityGenerator {
                    public function nextInventoryItemId(): \App\Inventory\Domain\ValueObject\InventoryItemId
                    {
                        throw new \LogicException('not used');
                    }
                    public function nextStockBatchId(): \App\Inventory\Domain\ValueObject\StockBatchId
                    {
                        return StockBatchId::fromString('019571bf-5d51-7000-b500-0000000004ff');
                    }
                    public function nextStockMovementId(): \App\Inventory\Domain\ValueObject\StockMovementId
                    {
                        throw new \LogicException('not used');
                    }
                    public function nextVendorId(): \App\Inventory\Domain\ValueObject\VendorId
                    {
                        throw new \LogicException('not used');
                    }
                    public function nextPurchaseOrderId(): \App\Inventory\Domain\ValueObject\PurchaseOrderId
                    {
                        throw new \LogicException('not used');
                    }
                    public function nextPurchaseOrderLineId(): \App\Inventory\Domain\ValueObject\PurchaseOrderLineId
                    {
                        throw new \LogicException('not used');
                    }
                },
            ),
        ];
    }

    /**
     * @param Closure(InventoryItem, ClockInterface): void $mutator
     */
    #[Test]
    #[DataProvider('mutators')]
    #[TestDox('Any mutator on an archived inventory item throws InventoryItemIsArchived: $_dataName.')]
    public function mutator_after_archive_throws(Closure $mutator): void
    {
        $item = $this->registerWithMaxParameters();
        $item->archive($this->clock);
        $item->releaseEvents();

        $this->expectException(InventoryItemIsArchived::class);

        $mutator($item, $this->clock);
    }

    private function registerWithMaxParameters(): InventoryItem
    {
        return InventoryItem::register(
            InventoryItemId::fromString(self::ITEM_ID),
            ListingId::fromString(self::LISTING_ID),
            VendorId::fromString(self::VENDOR_ID),
            PosColor::ofHex('#FF00AA'),
            true,
            true,
            ReorderThreshold::ofUnits(5),
            $this->clock,
        );
    }

    private function registerWithMinParameters(): InventoryItem
    {
        return InventoryItem::register(
            InventoryItemId::fromString(self::ITEM_ID),
            ListingId::fromString(self::LISTING_ID),
            null,
            PosColor::default(),
            false,
            false,
            ReorderThreshold::none(),
            $this->clock,
        );
    }
}
