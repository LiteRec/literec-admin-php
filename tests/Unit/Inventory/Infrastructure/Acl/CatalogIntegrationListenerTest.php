<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\Acl;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Integration\Event\LineSold;
use App\Inventory\Domain\Combo;
use App\Inventory\Domain\Event\StockMovementRecorded;
use App\Inventory\Domain\Exception\InsufficientStock;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\ComboGraphResolver;
use App\Inventory\Domain\ItemLink;
use App\Inventory\Domain\ValueObject\ComboComponent;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Infrastructure\Acl\CatalogIntegrationListener;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryCombos;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryInventoryItems;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryItemLinks;
use App\Inventory\Integration\Event\StockConsumptionFailed;
use App\Tests\Support\Fake\RecordingMessageBus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class CatalogIntegrationListenerTest extends TestCase
{
    private const ITEM_ID = '019571bf-5d51-7000-b500-000000006001';
    private const LISTING_ID = '019571bf-5d51-7000-b500-000000006002';
    private const BATCH_ID = '019571bf-5d51-7000-b500-000000006003';
    private const COMBO_ID = '019571bf-5d51-7000-b500-000000006101';
    private const COMPONENT_A = '019571bf-5d51-7000-b500-000000006201';
    private const COMPONENT_B = '019571bf-5d51-7000-b500-000000006202';
    private const COMPONENT_A_BATCH = '019571bf-5d51-7000-b500-000000006301';
    private const COMPONENT_B_BATCH = '019571bf-5d51-7000-b500-000000006302';
    private const LINK_ID = '019571bf-5d51-7000-b500-000000006401';
    private const LINKED_ITEM = '019571bf-5d51-7000-b500-000000006501';
    private const TRANSACTION_ID = 'tx-019571bf-5d51-7000-b500-000000006601';

    private InMemoryInventoryItems $items;
    private InMemoryCombos $combos;
    private InMemoryItemLinks $itemLinks;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;
    private CatalogIntegrationListener $listener;

    protected function setUp(): void
    {
        $this->items = new InMemoryInventoryItems();
        $this->combos = new InMemoryCombos();
        $this->itemLinks = new InMemoryItemLinks();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 12:00:00'));
        $this->listener = new CatalogIntegrationListener(
            $this->items,
            $this->combos,
            $this->itemLinks,
            $this->clock,
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Non-INVENTORY listings are silently ignored.')]
    public function non_inventory_listings_ignored(): void
    {
        ($this->listener)(new LineSold(
            listingId: self::LISTING_ID,
            listingKind: 'PROGRAM',
            listingCode: 'YOGA-101',
            quantity: 1,
            facilityCode: 'MAIN',
            transactionId: self::TRANSACTION_ID,
            occurredAt: $this->clock->now(),
        ));

        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    #[Test]
    #[TestDox('Unknown inventory item emits StockConsumptionFailed with UNKNOWN_INVENTORY_ITEM.')]
    public function unknown_inventory_item(): void
    {
        ($this->listener)($this->lineSoldFor(self::LISTING_ID, 'MAIN', 1));

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        $event = $messages[0];
        self::assertInstanceOf(StockConsumptionFailed::class, $event);
        self::assertSame(StockConsumptionFailed::REASON_UNKNOWN_INVENTORY_ITEM, $event->reasonCode);
        self::assertSame(self::TRANSACTION_ID, $event->transactionId);
        self::assertSame(self::LISTING_ID, $event->listingId);
        self::assertSame('MAIN', $event->facilityCode);
        self::assertNull($event->offendingInventoryItemId);
        self::assertNull($event->offendingLinkId);
    }

    #[Test]
    #[TestDox('Happy path: FIFO-consumes the matched inventory item and emits StockMovementRecorded.')]
    public function happy_path_consumes(): void
    {
        $this->seedItemWithStock(self::ITEM_ID, self::LISTING_ID, self::BATCH_ID, units: 5, costCents: 100);

        ($this->listener)($this->lineSoldFor(self::LISTING_ID, 'MAIN', 2));

        $loaded = $this->items->byId(InventoryItemId::fromString(self::ITEM_ID));
        self::assertSame(3, $loaded->totalOnHandAt(FacilityCode::fromString('MAIN'))->units);

        $domainEvents = array_filter(
            $this->eventBus->dispatchedMessages(),
            static fn ($m): bool => $m instanceof StockMovementRecorded,
        );
        self::assertCount(1, $domainEvents);
        self::assertSame([], array_filter(
            $this->eventBus->dispatchedMessages(),
            static fn ($m): bool => $m instanceof StockConsumptionFailed,
        ));
    }

    #[Test]
    #[TestDox('Combo expansion: consumes each component by qtyPerCombo * lineQuantity.')]
    public function combo_expansion_consumes_components(): void
    {
        // Master inventory item is the combo's listing-target (used for byListingId lookup).
        $this->seedItemWithStock(self::ITEM_ID, self::LISTING_ID, self::BATCH_ID, units: 5, costCents: 0);
        // Two component items each have their own stock.
        $this->seedItemWithStock(
            self::COMPONENT_A,
            '019571bf-5d51-7000-b500-000000006a01',
            self::COMPONENT_A_BATCH,
            units: 10,
            costCents: 100,
        );
        $this->seedItemWithStock(
            self::COMPONENT_B,
            '019571bf-5d51-7000-b500-000000006a02',
            self::COMPONENT_B_BATCH,
            units: 10,
            costCents: 200,
        );

        $cleanResolver = new readonly class implements ComboGraphResolver {
            public function isCombo(InventoryItemId $itemId): bool
            {
                return false;
            }

            public function componentsOf(InventoryItemId $itemId): array
            {
                return [];
            }
        };
        $combo = Combo::define(
            ComboId::fromString(self::COMBO_ID),
            ListingId::fromString(self::LISTING_ID),
            [
                new ComboComponent(InventoryItemId::fromString(self::COMPONENT_A), Quantity::ofUnits(1)),
                new ComboComponent(InventoryItemId::fromString(self::COMPONENT_B), Quantity::ofUnits(2)),
            ],
            $cleanResolver,
            $this->clock,
        );
        $combo->releaseEvents();
        $this->combos->add($combo);

        ($this->listener)($this->lineSoldFor(self::LISTING_ID, 'MAIN', 3));

        // Combo of (A:1, B:2) sold x3 → A consumes 3, B consumes 6.
        $a = $this->items->byId(InventoryItemId::fromString(self::COMPONENT_A));
        $b = $this->items->byId(InventoryItemId::fromString(self::COMPONENT_B));
        self::assertSame(7, $a->totalOnHandAt(FacilityCode::fromString('MAIN'))->units);
        self::assertSame(4, $b->totalOnHandAt(FacilityCode::fromString('MAIN'))->units);
    }

    #[Test]
    #[TestDox('Item-link violation blocks consumption and emits StockConsumptionFailed.')]
    public function link_violation_blocks_consumption(): void
    {
        $this->seedItemWithStock(self::ITEM_ID, self::LISTING_ID, self::BATCH_ID, units: 5, costCents: 0);
        $this->seedItemWithStock(
            self::LINKED_ITEM,
            '019571bf-5d51-7000-b500-000000006b01',
            '019571bf-5d51-7000-b500-000000006b02',
            units: 1,
            costCents: 0,
        );

        // reservedQuantity=100 far exceeds the linked item's 1-unit on-hand
        // stock at MAIN; wouldViolateAt() returns RESERVED_QUANTITY_BREACHED.
        // minRequired/maxPerPurchase are zero so they never fire here.
        $link = ItemLink::link(
            ItemLinkId::fromString(self::LINK_ID),
            InventoryItemId::fromString(self::ITEM_ID),
            InventoryItemId::fromString(self::LINKED_ITEM),
            Quantity::ofUnits(100),
            unlimited: false,
            minRequired: Quantity::zero(),
            maxPerPurchase: Quantity::zero(),
            includeUntil: null,
            clock: $this->clock,
        );
        $link->releaseEvents();
        $this->itemLinks->add($link);

        ($this->listener)($this->lineSoldFor(self::LISTING_ID, 'MAIN', 1));

        $loaded = $this->items->byId(InventoryItemId::fromString(self::ITEM_ID));
        self::assertSame(5, $loaded->totalOnHandAt(FacilityCode::fromString('MAIN'))->units, 'no stock change');

        $failures = array_values(array_filter(
            $this->eventBus->dispatchedMessages(),
            static fn ($m): bool => $m instanceof StockConsumptionFailed,
        ));
        self::assertCount(1, $failures);
        self::assertSame(StockConsumptionFailed::REASON_LINK_VIOLATION, $failures[0]->reasonCode);
        self::assertSame(self::LINK_ID, $failures[0]->offendingLinkId);
        self::assertSame(self::ITEM_ID, $failures[0]->offendingInventoryItemId);
        self::assertSame(self::TRANSACTION_ID, $failures[0]->transactionId);
    }

    #[Test]
    #[TestDox('Insufficient stock emits StockConsumptionFailed and rethrows for transaction rollback.')]
    public function insufficient_stock(): void
    {
        $this->seedItemWithStock(self::ITEM_ID, self::LISTING_ID, self::BATCH_ID, units: 2, costCents: 0);

        try {
            ($this->listener)($this->lineSoldFor(self::LISTING_ID, 'MAIN', 5));
            self::fail('Expected InsufficientStock to bubble up so the surrounding transaction can roll back.');
        } catch (InsufficientStock $e) {
            self::assertSame(5, $e->requested->units);
            self::assertSame(2, $e->available->units);
        }

        $failures = array_values(array_filter(
            $this->eventBus->dispatchedMessages(),
            static fn ($m): bool => $m instanceof StockConsumptionFailed,
        ));
        self::assertCount(1, $failures);
        self::assertSame(StockConsumptionFailed::REASON_INSUFFICIENT_STOCK, $failures[0]->reasonCode);
        self::assertSame(self::ITEM_ID, $failures[0]->offendingInventoryItemId);
        self::assertNull($failures[0]->offendingLinkId);
    }

    private function lineSoldFor(string $listingId, string $facility, int $qty): LineSold
    {
        return new LineSold(
            listingId: $listingId,
            listingKind: 'INVENTORY',
            listingCode: 'WIDGET-1',
            quantity: $qty,
            facilityCode: $facility,
            transactionId: self::TRANSACTION_ID,
            occurredAt: $this->clock->now(),
        );
    }

    private function seedItemWithStock(
        string $itemId,
        string $listingId,
        string $batchId,
        int $units,
        int $costCents,
    ): void {
        $item = InventoryItem::register(
            InventoryItemId::fromString($itemId),
            ListingId::fromString($listingId),
            null,
            PosColor::default(),
            true,
            false,
            ReorderThreshold::none(),
            $this->clock,
        );
        $item->receiveBatch(
            FacilityCode::fromString('MAIN'),
            Quantity::ofUnits($units),
            CostPerUnit::ofCents($costCents),
            null,
            null,
            StockBatchId::fromString($batchId),
            $this->clock,
        );
        $item->releaseEvents();
        $this->items->add($item);
    }
}
