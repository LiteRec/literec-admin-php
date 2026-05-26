<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Application\Command\CreatePurchaseOrder;
use App\Inventory\Application\Command\CreatePurchaseOrderHandler;
use App\Inventory\Application\Command\MarkPurchaseOrderSent;
use App\Inventory\Application\Command\MarkPurchaseOrderSentHandler;
use App\Inventory\Application\Command\ReceivePurchaseOrderLine;
use App\Inventory\Application\Command\ReceivePurchaseOrderLineHandler;
use App\Inventory\Application\Command\VerifyDelivery;
use App\Inventory\Application\Command\VerifyDeliveryHandler;
use App\Inventory\Domain\Event\PurchaseOrderDrafted;
use App\Inventory\Domain\Event\PurchaseOrderFullyReceived;
use App\Inventory\Domain\Event\PurchaseOrderLineReceived;
use App\Inventory\Domain\Event\PurchaseOrderSent;
use App\Inventory\Domain\Event\PurchaseOrderVerified;
use App\Inventory\Domain\Event\StockReceived;
use App\Inventory\Domain\Exception\PurchaseOrderLineOverReceipt;
use App\Inventory\Domain\Exception\PurchaseOrderNotFullyReceived;
use App\Inventory\Domain\Exception\PurchaseOrderNotSent;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\PurchaseOrderStatus;
use App\Inventory\Domain\Vendor;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\VendorCode;
use App\Inventory\Domain\ValueObject\VendorContact;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Domain\ValueObject\VendorName;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryInventoryItems;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryPurchaseOrders;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryVendors;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Fake\SequenceInventoryIdentityGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class PurchaseOrderHandlersTest extends TestCase
{
    private const PO_ID = '019571bf-5d51-7000-b500-000000002001';
    private const VENDOR_ID = '019571bf-5d51-7000-b500-000000002101';
    private const ITEM_ID = '019571bf-5d51-7000-b500-000000002201';
    private const LISTING_ID = '019571bf-5d51-7000-b500-000000002301';
    private const LINE_1 = '019571bf-5d51-7000-b500-000000002401';
    private const STOCK_BATCH = '019571bf-5d51-7000-b500-000000002501';

    private InMemoryPurchaseOrders $orders;
    private InMemoryVendors $vendors;
    private InMemoryInventoryItems $items;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;
    private SequenceInventoryIdentityGenerator $ids;

    protected function setUp(): void
    {
        $this->orders = new InMemoryPurchaseOrders();
        $this->vendors = new InMemoryVendors();
        $this->items = new InMemoryInventoryItems();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
        $this->ids = new SequenceInventoryIdentityGenerator(
            stockBatchIds: [StockBatchId::fromString(self::STOCK_BATCH)],
            purchaseOrderIds: [PurchaseOrderId::fromString(self::PO_ID)],
            purchaseOrderLineIds: [PurchaseOrderLineId::fromString(self::LINE_1)],
        );

        $this->vendors->add(Vendor::register(
            VendorId::fromString(self::VENDOR_ID),
            VendorCode::fromString('ACME'),
            VendorName::of('Acme'),
            VendorContact::of('Jane'),
            null,
            null,
            null,
            $this->clock,
        ));

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
        $item->releaseEvents();
        $this->items->add($item);
    }

    #[Test]
    #[TestDox('CreatePurchaseOrder drafts a PO with generated line ids, persists it, returns the PO id.')]
    public function create_purchase_order(): void
    {
        $handler = new CreatePurchaseOrderHandler(
            $this->orders,
            $this->vendors,
            $this->items,
            $this->ids,
            $this->clock,
            $this->eventBus,
        );

        $poId = ($handler)(new CreatePurchaseOrder(
            vendorId: self::VENDOR_ID,
            facilityCode: 'MAIN',
            lines: [['itemId' => self::ITEM_ID, 'orderedQuantityUnits' => 10, 'costPerUnitCents' => 250]],
        ));

        self::assertSame(self::PO_ID, $poId->value);

        $loaded = $this->orders->byId($poId);
        self::assertSame(PurchaseOrderStatus::Draft, $loaded->status());
        self::assertCount(1, $loaded->lines());

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(PurchaseOrderDrafted::class, $messages[0]);
    }

    #[Test]
    #[TestDox('MarkPurchaseOrderSent transitions the PO to Sent and dispatches PurchaseOrderSent.')]
    public function mark_sent(): void
    {
        $this->createDraftPo();
        $this->eventBus = new RecordingMessageBus();

        $handler = new MarkPurchaseOrderSentHandler($this->orders, $this->clock, $this->eventBus);

        ($handler)(new MarkPurchaseOrderSent(
            purchaseOrderId: self::PO_ID,
            sentAtIso: '2026-05-26T11:00:00+00:00',
            estimatedArrivalIso: '2026-05-30T12:00:00+00:00',
        ));

        $loaded = $this->orders->byId(PurchaseOrderId::fromString(self::PO_ID));
        self::assertSame(PurchaseOrderStatus::Sent, $loaded->status());

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(PurchaseOrderSent::class, $messages[0]);
    }

    #[Test]
    #[TestDox('ReceivePurchaseOrderLine creates a StockBatch with the line cost basis AND updates the PO.')]
    public function receive_line_creates_stock_batch(): void
    {
        $this->createSentPo();
        $this->eventBus = new RecordingMessageBus();

        $handler = new ReceivePurchaseOrderLineHandler(
            $this->orders,
            $this->items,
            $this->ids,
            $this->clock,
            $this->eventBus,
        );

        ($handler)(new ReceivePurchaseOrderLine(
            purchaseOrderId: self::PO_ID,
            lineId: self::LINE_1,
            receivedQuantityUnits: 10,
            receivedAtIso: '2026-05-26T13:00:00+00:00',
        ));

        // PO transitions to FullyReceived since only one line totaling 10.
        $loadedPo = $this->orders->byId(PurchaseOrderId::fromString(self::PO_ID));
        self::assertSame(PurchaseOrderStatus::FullyReceived, $loadedPo->status());

        // Inventory item gains a StockBatch with the PO line's cost basis.
        $loadedItem = $this->items->byId(InventoryItemId::fromString(self::ITEM_ID));
        self::assertSame(10, $loadedItem->totalOnHand()->units);
        $batches = $loadedItem->batches();
        self::assertCount(1, $batches);
        self::assertSame(250, $batches[0]->costPerUnit()->cents);
        self::assertNotNull($batches[0]->sourceLineId());
        self::assertSame(self::LINE_1, $batches[0]->sourceLineId()->value);

        // Events: PurchaseOrderLineReceived + PurchaseOrderFullyReceived + StockReceived.
        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(3, $messages);
        self::assertInstanceOf(PurchaseOrderLineReceived::class, $messages[0]);
        self::assertInstanceOf(PurchaseOrderFullyReceived::class, $messages[1]);
        self::assertInstanceOf(StockReceived::class, $messages[2]);
    }

    #[Test]
    #[TestDox('Partial receipt leaves PO in PartiallyReceived; only PurchaseOrderLineReceived + StockReceived fire.')]
    public function partial_receipt_keeps_po_partially_received(): void
    {
        $this->createSentPo();
        $this->eventBus = new RecordingMessageBus();

        $handler = new ReceivePurchaseOrderLineHandler(
            $this->orders,
            $this->items,
            $this->ids,
            $this->clock,
            $this->eventBus,
        );

        ($handler)(new ReceivePurchaseOrderLine(
            purchaseOrderId: self::PO_ID,
            lineId: self::LINE_1,
            receivedQuantityUnits: 4,
            receivedAtIso: '2026-05-26T13:00:00+00:00',
        ));

        $loadedPo = $this->orders->byId(PurchaseOrderId::fromString(self::PO_ID));
        self::assertSame(PurchaseOrderStatus::PartiallyReceived, $loadedPo->status());

        $loadedItem = $this->items->byId(InventoryItemId::fromString(self::ITEM_ID));
        self::assertSame(4, $loadedItem->totalOnHand()->units);

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(2, $messages, 'PurchaseOrderLineReceived + StockReceived; no PurchaseOrderFullyReceived');
        self::assertInstanceOf(PurchaseOrderLineReceived::class, $messages[0]);
        self::assertInstanceOf(StockReceived::class, $messages[1]);
    }

    #[Test]
    #[TestDox('Receiving on a Draft PO throws PurchaseOrderNotSent before any state change.')]
    public function receive_on_draft_throws(): void
    {
        $this->createDraftPo();
        $this->eventBus = new RecordingMessageBus();

        $handler = new ReceivePurchaseOrderLineHandler(
            $this->orders,
            $this->items,
            $this->ids,
            $this->clock,
            $this->eventBus,
        );

        $this->expectException(PurchaseOrderNotSent::class);

        ($handler)(new ReceivePurchaseOrderLine(
            purchaseOrderId: self::PO_ID,
            lineId: self::LINE_1,
            receivedQuantityUnits: 5,
            receivedAtIso: '2026-05-26T13:00:00+00:00',
        ));
    }

    #[Test]
    #[TestDox('Over-receipt throws PurchaseOrderLineOverReceipt and does not mutate the InventoryItem.')]
    public function over_receipt_throws(): void
    {
        $this->createSentPo();
        $this->eventBus = new RecordingMessageBus();

        $handler = new ReceivePurchaseOrderLineHandler(
            $this->orders,
            $this->items,
            $this->ids,
            $this->clock,
            $this->eventBus,
        );

        try {
            ($handler)(new ReceivePurchaseOrderLine(
                purchaseOrderId: self::PO_ID,
                lineId: self::LINE_1,
                receivedQuantityUnits: 11,
                receivedAtIso: '2026-05-26T13:00:00+00:00',
            ));
            self::fail('Expected PurchaseOrderLineOverReceipt.');
        } catch (PurchaseOrderLineOverReceipt) {
            // ok
        }

        $loadedItem = $this->items->byId(InventoryItemId::fromString(self::ITEM_ID));
        self::assertSame(0, $loadedItem->totalOnHand()->units, 'no StockBatch should be created');
    }

    #[Test]
    #[TestDox('VerifyDelivery before FullyReceived throws PurchaseOrderNotFullyReceived.')]
    public function verify_before_full_throws(): void
    {
        $this->createSentPo();
        $this->eventBus = new RecordingMessageBus();

        $handler = new VerifyDeliveryHandler($this->orders, $this->clock, $this->eventBus);

        $this->expectException(PurchaseOrderNotFullyReceived::class);

        ($handler)(new VerifyDelivery(
            purchaseOrderId: self::PO_ID,
            verifiedByUserId: '019571bf-5d51-7000-b500-000000002999',
            verifiedAtIso: '2026-05-26T14:00:00+00:00',
        ));
    }

    #[Test]
    #[TestDox('VerifyDelivery after FullyReceived transitions to Verified and dispatches PurchaseOrderVerified.')]
    public function verify_happy_path(): void
    {
        $this->createSentPo();
        $receiveHandler = new ReceivePurchaseOrderLineHandler(
            $this->orders,
            $this->items,
            $this->ids,
            $this->clock,
            new RecordingMessageBus(),
        );
        ($receiveHandler)(new ReceivePurchaseOrderLine(
            purchaseOrderId: self::PO_ID,
            lineId: self::LINE_1,
            receivedQuantityUnits: 10,
            receivedAtIso: '2026-05-26T13:00:00+00:00',
        ));

        $this->eventBus = new RecordingMessageBus();
        $handler = new VerifyDeliveryHandler($this->orders, $this->clock, $this->eventBus);

        $userId = '019571bf-5d51-7000-b500-000000002999';
        ($handler)(new VerifyDelivery(
            purchaseOrderId: self::PO_ID,
            verifiedByUserId: $userId,
            verifiedAtIso: '2026-05-26T14:00:00+00:00',
        ));

        $loaded = $this->orders->byId(PurchaseOrderId::fromString(self::PO_ID));
        self::assertSame(PurchaseOrderStatus::Verified, $loaded->status());
        self::assertSame($userId, $loaded->verifiedByUserId());

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        $event = $messages[0];
        self::assertInstanceOf(PurchaseOrderVerified::class, $event);
        self::assertSame($userId, $event->verifiedByUserId);
    }

    private function createDraftPo(): void
    {
        $handler = new CreatePurchaseOrderHandler(
            $this->orders,
            $this->vendors,
            $this->items,
            $this->ids,
            $this->clock,
            new RecordingMessageBus(),
        );
        ($handler)(new CreatePurchaseOrder(
            vendorId: self::VENDOR_ID,
            facilityCode: 'MAIN',
            lines: [['itemId' => self::ITEM_ID, 'orderedQuantityUnits' => 10, 'costPerUnitCents' => 250]],
        ));
    }

    private function createSentPo(): void
    {
        $this->createDraftPo();
        $sendHandler = new MarkPurchaseOrderSentHandler($this->orders, $this->clock, new RecordingMessageBus());
        ($sendHandler)(new MarkPurchaseOrderSent(
            purchaseOrderId: self::PO_ID,
            sentAtIso: '2026-05-26T11:00:00+00:00',
            estimatedArrivalIso: null,
        ));
    }
}
