<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Application\Command\UpdateInventoryItemSettings;
use App\Inventory\Application\Command\UpdateInventoryItemSettingsHandler;
use App\Inventory\Domain\Event\InventoryItemPosColorUpdated;
use App\Inventory\Domain\Event\InventoryItemPrimaryVendorUpdated;
use App\Inventory\Domain\Event\InventoryItemRentableChanged;
use App\Inventory\Domain\Event\InventoryItemReorderThresholdUpdated;
use App\Inventory\Domain\Event\InventoryItemTrackingChanged;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryInventoryItems;
use App\Tests\Support\Fake\RecordingMessageBus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Unit-level coverage of the LRA-86 Edit-dialog handler.
 *
 * Each test seeds a fresh in-memory aggregate, dispatches a single
 * UpdateInventoryItemSettings command, and asserts that the handler
 * forwards exactly the events that correspond to fields that actually
 * changed — no events for fields whose value already matched. The
 * aggregate's own no-op guards make this assertion trivial: the
 * handler simply calls every updater unconditionally and the
 * aggregate decides whether to record an event.
 */
#[Small]
final class UpdateInventoryItemSettingsHandlerTest extends TestCase
{
    private const string ITEM_ID = '019571bf-5d51-7000-b500-00000000aa01';
    private const string LISTING_ID = '019571bf-5d51-7000-b500-00000000aa02';
    private const string VENDOR_ID = '019571bf-5d51-7000-b500-00000000aa03';

    private InMemoryInventoryItems $items;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->items = new InMemoryInventoryItems();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-27 14:00:00'));
    }

    #[Test]
    #[TestDox('When no field changes the handler emits zero domain events.')]
    public function no_changes_emits_no_events(): void
    {
        $this->seedItem(
            posHex: '#ABCDEF',
            vendor: null,
            track: true,
            rentable: false,
            reorder: 5,
        );

        $this->handler()(new UpdateInventoryItemSettings(
            itemId: self::ITEM_ID,
            posColorHex: '#ABCDEF',
            primaryVendorId: null,
            trackInventory: true,
            rentable: false,
            reorderThresholdUnits: 5,
        ));

        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    #[Test]
    #[TestDox('Pos color update emits exactly the matching event.')]
    public function pos_color_change_emits_one_event(): void
    {
        $this->seedItem(posHex: '#ABCDEF', vendor: null, track: true, rentable: false, reorder: 5);

        $this->handler()(new UpdateInventoryItemSettings(
            itemId: self::ITEM_ID,
            posColorHex: '#112233',
            primaryVendorId: null,
            trackInventory: true,
            rentable: false,
            reorderThresholdUnits: 5,
        ));

        $events = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $events);
        self::assertInstanceOf(InventoryItemPosColorUpdated::class, $events[0]);
    }

    #[Test]
    #[TestDox('Setting a primary vendor for the first time emits one event.')]
    public function primary_vendor_assignment_emits_one_event(): void
    {
        $this->seedItem(posHex: '#ABCDEF', vendor: null, track: true, rentable: false, reorder: 5);

        $this->handler()(new UpdateInventoryItemSettings(
            itemId: self::ITEM_ID,
            posColorHex: '#ABCDEF',
            primaryVendorId: self::VENDOR_ID,
            trackInventory: true,
            rentable: false,
            reorderThresholdUnits: 5,
        ));

        $events = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $events);
        self::assertInstanceOf(InventoryItemPrimaryVendorUpdated::class, $events[0]);
    }

    #[Test]
    #[TestDox('Toggling tracking off and rentable on emits both flag events.')]
    public function flag_changes_emit_both_events(): void
    {
        $this->seedItem(posHex: '#ABCDEF', vendor: null, track: true, rentable: false, reorder: 5);

        $this->handler()(new UpdateInventoryItemSettings(
            itemId: self::ITEM_ID,
            posColorHex: '#ABCDEF',
            primaryVendorId: null,
            trackInventory: false,
            rentable: true,
            reorderThresholdUnits: 5,
        ));

        $eventTypes = array_map(static fn (object $e): string => $e::class, $this->eventBus->dispatchedMessages());
        self::assertContains(InventoryItemTrackingChanged::class, $eventTypes);
        self::assertContains(InventoryItemRentableChanged::class, $eventTypes);
        self::assertCount(2, $eventTypes);
    }

    #[Test]
    #[TestDox('Changing the reorder threshold emits one event.')]
    public function reorder_threshold_update_emits_one_event(): void
    {
        $this->seedItem(posHex: '#ABCDEF', vendor: null, track: true, rentable: false, reorder: 5);

        $this->handler()(new UpdateInventoryItemSettings(
            itemId: self::ITEM_ID,
            posColorHex: '#ABCDEF',
            primaryVendorId: null,
            trackInventory: true,
            rentable: false,
            reorderThresholdUnits: 42,
        ));

        $events = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $events);
        self::assertInstanceOf(InventoryItemReorderThresholdUpdated::class, $events[0]);
    }

    #[Test]
    #[TestDox('Null primary vendor on an item that has none is a no-op.')]
    public function null_primary_vendor_on_no_vendor_item_is_noop(): void
    {
        $this->seedItem(posHex: '#ABCDEF', vendor: null, track: true, rentable: false, reorder: 5);

        $this->handler()(new UpdateInventoryItemSettings(
            itemId: self::ITEM_ID,
            posColorHex: '#ABCDEF',
            primaryVendorId: null,
            trackInventory: true,
            rentable: false,
            reorderThresholdUnits: 5,
        ));

        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    private function handler(): UpdateInventoryItemSettingsHandler
    {
        return new UpdateInventoryItemSettingsHandler(
            inventoryItems: $this->items,
            clock: $this->clock,
            eventBus: $this->eventBus,
        );
    }

    private function seedItem(
        string $posHex,
        ?string $vendor,
        bool $track,
        bool $rentable,
        int $reorder,
    ): void {
        $item = InventoryItem::register(
            id: InventoryItemId::fromString(self::ITEM_ID),
            listingId: ListingId::fromString(self::LISTING_ID),
            primaryVendorId: $vendor !== null ? VendorId::fromString($vendor) : null,
            posColor: PosColor::ofHex($posHex),
            trackInventory: $track,
            rentable: $rentable,
            reorderThreshold: ReorderThreshold::ofUnits($reorder),
            clock: $this->clock,
        );
        $item->releaseEvents();
        $this->items->add($item);
    }
}
