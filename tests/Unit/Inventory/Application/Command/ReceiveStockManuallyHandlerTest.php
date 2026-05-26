<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Application\Command\ReceiveStockManually;
use App\Inventory\Application\Command\ReceiveStockManuallyHandler;
use App\Inventory\Domain\Event\StockReceived;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryInventoryItems;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Fake\SequenceInventoryIdentityGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[Small]
final class ReceiveStockManuallyHandlerTest extends TestCase
{
    private const ITEM = '019571bf-5d51-7000-b500-000000001100';
    private const LISTING = '019571bf-5d51-7000-b500-000000001101';
    private const BATCH = '019571bf-5d51-7000-b500-000000001102';

    private InMemoryInventoryItems $items;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;
    private SequenceInventoryIdentityGenerator $ids;
    private ReceiveStockManuallyHandler $handler;

    protected function setUp(): void
    {
        $this->items = new InMemoryInventoryItems();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 09:00:00'));
        $this->ids = new SequenceInventoryIdentityGenerator(
            stockBatchIds: [StockBatchId::fromString(self::BATCH)],
        );
        $this->handler = new ReceiveStockManuallyHandler(
            $this->items,
            $this->ids,
            $this->clock,
            $this->eventBus,
        );

        $item = InventoryItem::register(
            InventoryItemId::fromString(self::ITEM),
            ListingId::fromString(self::LISTING),
            null,
            PosColor::default(),
            true,
            false,
            ReorderThreshold::none(),
            $this->clock,
        );
        $item->releaseEvents();
        $this->items->add($item);
    }

    #[Test]
    #[TestDox('Receives a batch at the requested facility and dispatches StockReceived post-commit.')]
    public function happy_path(): void
    {
        ($this->handler)(new ReceiveStockManually(
            itemId: self::ITEM,
            facilityCode: 'MAIN',
            quantityUnits: 12,
            costPerUnitCents: 350,
            comment: 'Walk-in vendor delivery',
            purchaseOrderLineId: null,
        ));

        $loaded = $this->items->byId(InventoryItemId::fromString(self::ITEM));
        self::assertSame(12, $loaded->totalOnHand()->units);

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        $event = $messages[0];
        self::assertInstanceOf(StockReceived::class, $event);
        self::assertSame(self::BATCH, $event->stockBatchId->value);
        self::assertSame(12, $event->quantity->units);
        self::assertSame(350, $event->costPerUnit->cents);
        self::assertNotNull($event->comments);
        self::assertSame('Walk-in vendor delivery', $event->comments->value);
        self::assertSame(self::ITEM, $event->inventoryItemId->value);
        self::assertSame('MAIN', $event->facilityCode->value);
        self::assertNull($event->sourceLineId);

        $envelopes = $this->eventBus->envelopes();
        self::assertNotNull($envelopes[0]->last(DispatchAfterCurrentBusStamp::class));
    }

    #[Test]
    #[TestDox('Carries the PurchaseOrderLineId through when the caller supplies one.')]
    public function carries_purchase_order_line_id(): void
    {
        $poLineId = '019571bf-5d51-7000-b500-000000001199';

        ($this->handler)(new ReceiveStockManually(
            itemId: self::ITEM,
            facilityCode: 'MAIN',
            quantityUnits: 5,
            costPerUnitCents: 200,
            comment: null,
            purchaseOrderLineId: $poLineId,
        ));

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        $event = $messages[0];
        self::assertInstanceOf(StockReceived::class, $event);
        self::assertNotNull($event->sourceLineId);
        self::assertSame($poLineId, $event->sourceLineId->value);
    }

    #[Test]
    #[TestDox('Throws InventoryItemNotFound when the target inventory item does not exist.')]
    public function unknown_item_throws(): void
    {
        $this->expectException(InventoryItemNotFound::class);

        ($this->handler)(new ReceiveStockManually(
            itemId: '019571bf-5d51-7000-b500-0000000011ff',
            facilityCode: 'MAIN',
            quantityUnits: 1,
            costPerUnitCents: 100,
            comment: null,
            purchaseOrderLineId: null,
        ));
    }
}
