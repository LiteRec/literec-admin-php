<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Query;

use App\Inventory\Application\Query\ListPurchaseOrders;
use App\Inventory\Application\Query\ListPurchaseOrdersHandler;
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
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryPurchaseOrders;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class ListPurchaseOrdersHandlerTest extends TestCase
{
    private const string PO_A = '019571bf-5d51-7000-b500-000000009001';
    private const string PO_B = '019571bf-5d51-7000-b500-000000009002';
    private const string PO_C = '019571bf-5d51-7000-b500-000000009003';
    private const string VENDOR_A = '019571bf-5d51-7000-b500-000000009101';
    private const string VENDOR_B = '019571bf-5d51-7000-b500-000000009102';
    private const string ITEM_ID = '019571bf-5d51-7000-b500-000000009201';
    private const string LINE_A = '019571bf-5d51-7000-b500-000000009301';
    private const string LINE_B = '019571bf-5d51-7000-b500-000000009302';
    private const string LINE_C = '019571bf-5d51-7000-b500-000000009303';
    private const string FACILITY_MAIN = 'MAIN';
    private const string FACILITY_LAKE = 'LAKESIDE';

    private InMemoryPurchaseOrders $orders;
    private MockClock $clock;
    private ListPurchaseOrdersHandler $handler;

    protected function setUp(): void
    {
        $this->orders = new InMemoryPurchaseOrders();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
        $this->handler = new ListPurchaseOrdersHandler($this->orders);
    }

    #[Test]
    #[TestDox('With no filters returns all POs and the totals carry into the page.')]
    public function returns_all_when_no_filters(): void
    {
        $this->seedThree();

        $page = ($this->handler)(new ListPurchaseOrders());

        self::assertSame(3, $page->totalCount);
        self::assertCount(3, $page->items);
        $first = $page->items[0];
        self::assertSame(1, $first->lineCount);
    }

    #[Test]
    #[TestDox('Vendor filter narrows the list to that vendor only.')]
    public function vendor_filter(): void
    {
        $this->seedThree();

        $page = ($this->handler)(new ListPurchaseOrders(vendorId: self::VENDOR_B));

        self::assertSame(1, $page->totalCount);
        self::assertCount(1, $page->items);
        self::assertSame(self::PO_C, $page->items[0]->purchaseOrderId);
    }

    #[Test]
    #[TestDox('Status filter accepts any PurchaseOrderStatus value.')]
    public function status_filter(): void
    {
        $this->seedThree();
        $po = $this->orders->byId(PurchaseOrderId::fromString(self::PO_A));
        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock);
        $this->orders->save($po);

        $page = ($this->handler)(new ListPurchaseOrders(status: PurchaseOrderStatus::Sent->value));

        self::assertSame(1, $page->totalCount);
        self::assertSame(self::PO_A, $page->items[0]->purchaseOrderId);
    }

    #[Test]
    #[TestDox('Unknown status string disables the status dimension rather than 400-ing.')]
    public function unknown_status_disables_filter(): void
    {
        $this->seedThree();

        $page = ($this->handler)(new ListPurchaseOrders(status: 'banana'));

        self::assertSame(3, $page->totalCount);
    }

    #[Test]
    #[TestDox('Facility filter narrows the list to that facility only.')]
    public function facility_filter(): void
    {
        $this->seedThree();

        $page = ($this->handler)(new ListPurchaseOrders(facilityCode: self::FACILITY_LAKE));

        self::assertSame(1, $page->totalCount);
        self::assertSame(self::PO_B, $page->items[0]->purchaseOrderId);
    }

    private function seedThree(): void
    {
        $a = $this->makeDraft(self::PO_A, self::VENDOR_A, self::FACILITY_MAIN, self::LINE_A);
        $this->orders->add($a);

        $b = $this->makeDraft(self::PO_B, self::VENDOR_A, self::FACILITY_LAKE, self::LINE_B);
        $this->orders->add($b);

        $c = $this->makeDraft(self::PO_C, self::VENDOR_B, self::FACILITY_MAIN, self::LINE_C);
        $this->orders->add($c);
    }

    private function makeDraft(string $poId, string $vendorId, string $facility, string $lineId): PurchaseOrder
    {
        $po = PurchaseOrder::createDraft(
            PurchaseOrderId::fromString($poId),
            VendorId::fromString($vendorId),
            FacilityCode::fromString($facility),
            [
                new PurchaseOrderLineDraft(
                    PurchaseOrderLineId::fromString($lineId),
                    InventoryItemId::fromString(self::ITEM_ID),
                    Quantity::ofUnits(5),
                    CostPerUnit::ofCents(100),
                ),
            ],
            $this->clock,
        );
        $po->releaseEvents();

        return $po;
    }
}
