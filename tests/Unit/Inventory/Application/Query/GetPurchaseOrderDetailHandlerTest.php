<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Query;

use App\Inventory\Application\Query\GetPurchaseOrderDetail;
use App\Inventory\Application\Query\GetPurchaseOrderDetailHandler;
use App\Inventory\Domain\Exception\PurchaseOrderNotFound;
use App\Inventory\Domain\PurchaseOrder;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineDraft;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryPurchaseOrders;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class GetPurchaseOrderDetailHandlerTest extends TestCase
{
    private const string PO_ID = '019571bf-5d51-7000-b500-000000008001';
    private const string VENDOR_ID = '019571bf-5d51-7000-b500-000000008101';
    private const string ITEM_ID = '019571bf-5d51-7000-b500-000000008201';
    private const string LINE_1 = '019571bf-5d51-7000-b500-000000008301';
    private const string LINE_2 = '019571bf-5d51-7000-b500-000000008302';
    private const string FACILITY = 'MAIN';
    private const string USER_ID = 'verifier@example.test';

    private InMemoryPurchaseOrders $orders;
    private MockClock $clock;
    private GetPurchaseOrderDetailHandler $handler;

    protected function setUp(): void
    {
        $this->orders = new InMemoryPurchaseOrders();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
        $this->handler = new GetPurchaseOrderDetailHandler($this->orders);
    }

    #[Test]
    #[TestDox('Draft PO allows only the "send" transition and exposes editable lines.')]
    public function draft_allows_send_only(): void
    {
        $this->seedDraft();

        $detail = ($this->handler)(new GetPurchaseOrderDetail(self::PO_ID));

        self::assertSame('draft', $detail->status);
        self::assertSame(['send'], $detail->allowedTransitions);
        self::assertTrue($detail->isLineEditable);
        self::assertCount(2, $detail->lines);
        self::assertSame(10, $detail->lines[0]->orderedUnits);
        self::assertSame(0, $detail->lines[0]->receivedUnits);
        self::assertSame(10, $detail->lines[0]->remainingUnits);
        self::assertFalse($detail->lines[0]->isFullyReceived);
    }

    #[Test]
    #[TestDox('Sent PO allows only the "receiveLine" transition.')]
    public function sent_allows_receive_line_only(): void
    {
        $po = $this->seedDraft();
        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock);
        $this->orders->save($po);

        $detail = ($this->handler)(new GetPurchaseOrderDetail(self::PO_ID));

        self::assertSame('sent', $detail->status);
        self::assertSame(['receiveLine'], $detail->allowedTransitions);
        self::assertFalse($detail->isLineEditable);
        self::assertNotNull($detail->sentAtIso);
    }

    #[Test]
    #[TestDox('PartiallyReceived PO still allows "receiveLine".')]
    public function partially_received_allows_receive_line(): void
    {
        $po = $this->seedDraft();
        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock);
        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_1),
            Quantity::ofUnits(3),
            new DateTimeImmutable('2026-05-26 12:00:00'),
            $this->clock,
        );
        $this->orders->save($po);

        $detail = ($this->handler)(new GetPurchaseOrderDetail(self::PO_ID));

        self::assertSame('partially_received', $detail->status);
        self::assertSame(['receiveLine'], $detail->allowedTransitions);
        self::assertSame(3, $detail->lines[0]->receivedUnits);
        self::assertSame(7, $detail->lines[0]->remainingUnits);
    }

    #[Test]
    #[TestDox('FullyReceived PO allows only the "verify" transition.')]
    public function fully_received_allows_verify_only(): void
    {
        $po = $this->seedDraft();
        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock);
        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_1),
            Quantity::ofUnits(10),
            new DateTimeImmutable('2026-05-26 12:00:00'),
            $this->clock,
        );
        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_2),
            Quantity::ofUnits(5),
            new DateTimeImmutable('2026-05-26 12:30:00'),
            $this->clock,
        );
        $this->orders->save($po);

        $detail = ($this->handler)(new GetPurchaseOrderDetail(self::PO_ID));

        self::assertSame('fully_received', $detail->status);
        self::assertSame(['verify'], $detail->allowedTransitions);
        self::assertTrue($detail->lines[0]->isFullyReceived);
        self::assertTrue($detail->lines[1]->isFullyReceived);
    }

    #[Test]
    #[TestDox('Verified PO has no further allowed transitions and exposes verifier details.')]
    public function verified_has_no_transitions(): void
    {
        $po = $this->seedDraft();
        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock);
        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_1),
            Quantity::ofUnits(10),
            new DateTimeImmutable('2026-05-26 12:00:00'),
            $this->clock,
        );
        $po->receiveLine(
            PurchaseOrderLineId::fromString(self::LINE_2),
            Quantity::ofUnits(5),
            new DateTimeImmutable('2026-05-26 12:30:00'),
            $this->clock,
        );
        $po->verifyDelivery(self::USER_ID, new DateTimeImmutable('2026-05-26 13:00:00'), $this->clock);
        $this->orders->save($po);

        $detail = ($this->handler)(new GetPurchaseOrderDetail(self::PO_ID));

        self::assertSame('verified', $detail->status);
        self::assertSame([], $detail->allowedTransitions);
        self::assertSame(self::USER_ID, $detail->verifiedByUserId);
        self::assertNotNull($detail->verifiedAtIso);
    }

    #[Test]
    #[TestDox('Unknown PO id surfaces PurchaseOrderNotFound to the caller.')]
    public function missing_po_throws(): void
    {
        $this->expectException(PurchaseOrderNotFound::class);

        ($this->handler)(new GetPurchaseOrderDetail(self::PO_ID));
    }

    private function seedDraft(): PurchaseOrder
    {
        $po = PurchaseOrder::createDraft(
            PurchaseOrderId::fromString(self::PO_ID),
            VendorId::fromString(self::VENDOR_ID),
            FacilityCode::fromString(self::FACILITY),
            [
                new PurchaseOrderLineDraft(
                    PurchaseOrderLineId::fromString(self::LINE_1),
                    InventoryItemId::fromString(self::ITEM_ID),
                    Quantity::ofUnits(10),
                    CostPerUnit::ofCents(250),
                ),
                new PurchaseOrderLineDraft(
                    PurchaseOrderLineId::fromString(self::LINE_2),
                    InventoryItemId::fromString(self::ITEM_ID),
                    Quantity::ofUnits(5),
                    CostPerUnit::ofCents(500),
                ),
            ],
            $this->clock,
        );
        $po->releaseEvents();
        $this->orders->add($po);

        return $po;
    }
}
