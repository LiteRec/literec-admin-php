<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Inventory\Domain\Event\PurchaseOrderDrafted;
use App\Inventory\Domain\Event\PurchaseOrderFullyReceived;
use App\Inventory\Domain\Event\PurchaseOrderLineReceived;
use App\Inventory\Domain\Event\PurchaseOrderSent;
use App\Inventory\Domain\Event\PurchaseOrderVerified;
use App\Inventory\Domain\Exception\PurchaseOrderLineNotFound;
use App\Inventory\Domain\Exception\PurchaseOrderLineOverReceipt;
use App\Inventory\Domain\Exception\PurchaseOrderNotDraft;
use App\Inventory\Domain\Exception\PurchaseOrderNotFullyReceived;
use App\Inventory\Domain\Exception\PurchaseOrderNotSent;
use App\Inventory\Domain\Exception\PurchaseOrderRequiresLines;
use App\Inventory\Domain\PurchaseOrder;
use App\Inventory\Domain\PurchaseOrderStatus;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineDraft;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\VendorId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class PurchaseOrderTest extends TestCase
{
    private const PO_ID = '019571bf-5d51-7000-b500-000000000900';
    private const VENDOR_ID = '019571bf-5d51-7000-b500-000000000901';
    private const FACILITY = 'MAIN';
    private const ITEM_1 = '019571bf-5d51-7000-b500-000000000a01';
    private const ITEM_2 = '019571bf-5d51-7000-b500-000000000a02';
    private const LINE_1 = '019571bf-5d51-7000-b500-000000000b01';
    private const LINE_2 = '019571bf-5d51-7000-b500-000000000b02';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
    }

    #[Test]
    #[TestDox('createDraft() records PurchaseOrderDrafted and copies each line into the aggregate.')]
    public function create_draft_records_event(): void
    {
        $po = $this->draftWithTwoLines();

        self::assertSame(PurchaseOrderStatus::Draft, $po->status());
        self::assertCount(2, $po->lines());

        $events = $po->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(PurchaseOrderDrafted::class, $event);
        self::assertSame(self::PO_ID, $event->purchaseOrderId->value);
        self::assertSame(self::VENDOR_ID, $event->vendorId->value);
        self::assertSame(self::FACILITY, $event->facilityCode->value);
        self::assertCount(2, $event->lines);
    }

    #[Test]
    #[TestDox('createDraft() with no lines throws PurchaseOrderRequiresLines.')]
    public function create_draft_with_no_lines_throws(): void
    {
        $this->expectException(PurchaseOrderRequiresLines::class);

        PurchaseOrder::createDraft(
            PurchaseOrderId::fromString(self::PO_ID),
            VendorId::fromString(self::VENDOR_ID),
            FacilityCode::fromString(self::FACILITY),
            [],
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('send() transitions Draft → Sent and emits PurchaseOrderSent.')]
    public function send_transitions_to_sent(): void
    {
        $po = $this->draftWithTwoLines();
        $po->releaseEvents();

        $sentAt = new DateTimeImmutable('2026-05-26 11:00:00');
        $eta = new DateTimeImmutable('2026-05-30 12:00:00');

        $this->clock->modify('+1 hour');
        $po->send($sentAt, $eta, $this->clock);

        self::assertSame(PurchaseOrderStatus::Sent, $po->status());
        self::assertEquals($sentAt, $po->sentAt());
        self::assertEquals($eta, $po->estimatedArrival());

        $events = $po->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(PurchaseOrderSent::class, $event);
        self::assertEquals($sentAt, $event->sentAt);
        self::assertEquals($eta, $event->estimatedArrival);
    }

    #[Test]
    #[TestDox('send() on a non-Draft PO throws PurchaseOrderNotDraft.')]
    public function send_on_non_draft_throws(): void
    {
        $po = $this->draftWithTwoLines();
        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock);
        $po->releaseEvents();

        $this->expectException(PurchaseOrderNotDraft::class);

        $po->send(new DateTimeImmutable('2026-05-26 12:00:00'), null, $this->clock);
    }

    #[Test]
    #[TestDox('receiveLine() on a non-Sent PO (still Draft) throws PurchaseOrderNotSent.')]
    public function receive_line_on_draft_throws(): void
    {
        $po = $this->draftWithTwoLines();

        $this->expectException(PurchaseOrderNotSent::class);

        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_1),
            Quantity::ofUnits(1),
            new DateTimeImmutable('2026-05-26 12:00:00'),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('receiveLine() partial quantity transitions Sent → PartiallyReceived; emits PurchaseOrderLineReceived.')]
    public function partial_receive_transitions_to_partially_received(): void
    {
        $po = $this->draftWithTwoLines();
        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock);
        $po->releaseEvents();

        $receivedAt = new DateTimeImmutable('2026-05-26 13:00:00');
        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_1),
            Quantity::ofUnits(5),
            $receivedAt,
            $this->clock,
        );

        self::assertSame(PurchaseOrderStatus::PartiallyReceived, $po->status());

        $events = $po->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(PurchaseOrderLineReceived::class, $event);
        self::assertSame(self::LINE_1, $event->lineId->value);
        self::assertSame(self::ITEM_1, $event->itemId->value);
        self::assertSame(self::FACILITY, $event->facilityCode->value);
        self::assertSame(5, $event->quantityReceived->units);
        self::assertSame(250, $event->costPerUnit->cents);
        self::assertEquals($receivedAt, $event->receivedAt);
    }

    #[Test]
    #[TestDox('receiveLine() completing the last line transitions to FullyReceived; emits PurchaseOrderFullyReceived.')]
    public function final_receive_transitions_to_fully_received(): void
    {
        $po = $this->draftWithTwoLines();
        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock);
        $po->releaseEvents();

        $receivedAt = new DateTimeImmutable('2026-05-26 13:00:00');

        // Receive both lines fully (10 and 5 ordered).
        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_1),
            Quantity::ofUnits(10),
            $receivedAt,
            $this->clock,
        );
        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_2),
            Quantity::ofUnits(5),
            $receivedAt,
            $this->clock,
        );

        self::assertSame(PurchaseOrderStatus::FullyReceived, $po->status());

        $events = $po->releaseEvents();
        // 1st receive: 1 PurchaseOrderLineReceived (line 1)
        // 2nd receive: 1 PurchaseOrderLineReceived (line 2) + 1 PurchaseOrderFullyReceived
        self::assertCount(3, $events);
        self::assertInstanceOf(PurchaseOrderLineReceived::class, $events[0]);
        self::assertInstanceOf(PurchaseOrderLineReceived::class, $events[1]);
        self::assertInstanceOf(PurchaseOrderFullyReceived::class, $events[2]);
    }

    #[Test]
    #[TestDox('receiveLine() past the ordered quantity throws PurchaseOrderLineOverReceipt with payload.')]
    public function over_receipt_throws(): void
    {
        $po = $this->draftWithTwoLines();
        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock);
        $po->releaseEvents();

        $receivedAt = new DateTimeImmutable('2026-05-26 13:00:00');
        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_1),
            Quantity::ofUnits(7),
            $receivedAt,
            $this->clock,
        );
        $po->releaseEvents();

        try {
            $po->receiveLine(
                PurchaseOrderLineId::fromString(self::LINE_1),
                Quantity::ofUnits(4),
                $receivedAt,
                $this->clock,
            );
            self::fail('Expected PurchaseOrderLineOverReceipt.');
        } catch (PurchaseOrderLineOverReceipt $e) {
            self::assertSame(self::LINE_1, $e->lineId->value);
            self::assertSame(10, $e->ordered->units);
            self::assertSame(7, $e->alreadyReceived->units);
            self::assertSame(4, $e->attempted->units);
        }

        self::assertSame([], $po->releaseEvents());
    }

    #[Test]
    #[TestDox('receiveLine() for an unknown line id throws PurchaseOrderLineNotFound.')]
    public function receive_unknown_line_throws(): void
    {
        $po = $this->draftWithTwoLines();
        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock);

        $this->expectException(PurchaseOrderLineNotFound::class);

        $po->receiveLine(
            PurchaseOrderLineId::fromString('019571bf-5d51-7000-b500-0000000000ff'),
            Quantity::ofUnits(1),
            new DateTimeImmutable('2026-05-26 13:00:00'),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('verifyDelivery() before FullyReceived throws PurchaseOrderNotFullyReceived.')]
    public function verify_before_fully_received_throws(): void
    {
        $po = $this->draftWithTwoLines();
        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock);
        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_1),
            Quantity::ofUnits(5),
            new DateTimeImmutable('2026-05-26 13:00:00'),
            $this->clock,
        );

        $this->expectException(PurchaseOrderNotFullyReceived::class);

        $po->verifyDelivery(
            '019571bf-5d51-7000-b500-0000000000aa',
            new DateTimeImmutable('2026-05-26 14:00:00'),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('verifyDelivery() after FullyReceived emits PurchaseOrderVerified and transitions to Verified.')]
    public function verify_after_full_receive_emits_event(): void
    {
        $po = $this->fullyReceived();
        $po->releaseEvents();

        $verifiedAt = new DateTimeImmutable('2026-05-26 14:00:00');
        $userId = '019571bf-5d51-7000-b500-0000000000aa';
        $po->verifyDelivery($userId, $verifiedAt, $this->clock);

        self::assertSame(PurchaseOrderStatus::Verified, $po->status());
        self::assertSame($userId, $po->verifiedByUserId());
        self::assertEquals($verifiedAt, $po->verifiedAt());

        $events = $po->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(PurchaseOrderVerified::class, $event);
        self::assertSame($userId, $event->verifiedByUserId);
        self::assertEquals($verifiedAt, $event->verifiedAt);
    }

    private function draftWithTwoLines(): PurchaseOrder
    {
        $lines = [
            new PurchaseOrderLineDraft(
                PurchaseOrderLineId::fromString(self::LINE_1),
                InventoryItemId::fromString(self::ITEM_1),
                Quantity::ofUnits(10),
                CostPerUnit::ofCents(250),
            ),
            new PurchaseOrderLineDraft(
                PurchaseOrderLineId::fromString(self::LINE_2),
                InventoryItemId::fromString(self::ITEM_2),
                Quantity::ofUnits(5),
                CostPerUnit::ofCents(500),
            ),
        ];

        return PurchaseOrder::createDraft(
            PurchaseOrderId::fromString(self::PO_ID),
            VendorId::fromString(self::VENDOR_ID),
            FacilityCode::fromString(self::FACILITY),
            $lines,
            $this->clock,
        );
    }

    private function fullyReceived(): PurchaseOrder
    {
        $po = $this->draftWithTwoLines();
        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock);
        $receivedAt = new DateTimeImmutable('2026-05-26 13:00:00');
        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_1),
            Quantity::ofUnits(10),
            $receivedAt,
            $this->clock,
        );
        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_2),
            Quantity::ofUnits(5),
            $receivedAt,
            $this->clock,
        );

        return $po;
    }
}
