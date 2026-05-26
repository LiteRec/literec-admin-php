<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Inventory\Domain\Exception\PurchaseOrderNotFound;
use App\Inventory\Domain\PurchaseOrder;
use App\Inventory\Domain\PurchaseOrders;
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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared behavioural contract for any {@see PurchaseOrders} adapter.
 *
 * Concrete test classes ({@see InMemoryPurchaseOrdersContractTest},
 * {@see DoctrinePurchaseOrdersContractTest}) use this trait so the two
 * implementations cannot drift apart.
 */
trait PurchaseOrdersContractCases
{
    private const PO_A = '019571bf-5d51-7000-b500-000000000c01';
    private const PO_B = '019571bf-5d51-7000-b500-000000000c02';
    private const PO_C = '019571bf-5d51-7000-b500-000000000c03';
    private const VENDOR_A = '019571bf-5d51-7000-b500-000000000d01';
    private const VENDOR_B = '019571bf-5d51-7000-b500-000000000d02';
    private const ITEM_A = '019571bf-5d51-7000-b500-000000000e01';
    private const LINE_A1 = '019571bf-5d51-7000-b500-000000000fa1';
    private const LINE_A2 = '019571bf-5d51-7000-b500-000000000fa2';
    private const LINE_B1 = '019571bf-5d51-7000-b500-000000000fb1';
    private const LINE_C1 = '019571bf-5d51-7000-b500-000000000fc1';
    private const FACILITY_MAIN = 'MAIN';
    private const FACILITY_LAKE = 'LAKESIDE';

    abstract protected function purchaseOrders(): PurchaseOrders;

    abstract protected function clock(): MockClock;

    #[Test]
    #[TestDox('add() then byId() round-trips a draft PO with all its lines in creation order.')]
    public function add_then_by_id_round_trips(): void
    {
        $po = $this->makeDraft(self::PO_A, self::VENDOR_A, self::FACILITY_MAIN, [
            [self::LINE_A1, 10, 250],
            [self::LINE_A2, 5, 500],
        ]);
        $this->purchaseOrders()->add($po);

        $loaded = $this->purchaseOrders()->byId(PurchaseOrderId::fromString(self::PO_A));

        self::assertTrue($loaded->id()->equals($po->id()));
        self::assertSame(self::VENDOR_A, $loaded->vendorId()->value);
        self::assertSame(self::FACILITY_MAIN, $loaded->facilityCode()->value);
        self::assertSame(PurchaseOrderStatus::Draft, $loaded->status());

        $lines = $loaded->lines();
        self::assertCount(2, $lines);
        self::assertSame(self::LINE_A1, $lines[0]->id()->value);
        self::assertSame(10, $lines[0]->orderedQuantity()->units);
        self::assertSame(self::LINE_A2, $lines[1]->id()->value);
        self::assertSame(5, $lines[1]->orderedQuantity()->units);
    }

    #[Test]
    #[TestDox('byId() throws PurchaseOrderNotFound when no purchase order has the given id.')]
    public function by_id_missing_throws(): void
    {
        $this->expectException(PurchaseOrderNotFound::class);

        $this->purchaseOrders()->byId(PurchaseOrderId::fromString(self::PO_A));
    }

    #[Test]
    #[TestDox('save() persists subsequent updates after the PO has been added.')]
    public function save_persists_updates(): void
    {
        $po = $this->makeDraft(self::PO_A, self::VENDOR_A, self::FACILITY_MAIN, [
            [self::LINE_A1, 5, 250],
        ]);
        $this->purchaseOrders()->add($po);

        $po->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock());
        $this->purchaseOrders()->save($po);

        $loaded = $this->purchaseOrders()->byId(PurchaseOrderId::fromString(self::PO_A));
        self::assertSame(PurchaseOrderStatus::Sent, $loaded->status());
    }

    #[Test]
    #[TestDox('save() throws PurchaseOrderNotFound for an aggregate that was never persisted.')]
    public function save_unknown_throws(): void
    {
        $orphan = $this->makeDraft(self::PO_A, self::VENDOR_A, self::FACILITY_MAIN, [
            [self::LINE_A1, 1, 100],
        ]);

        $this->expectException(PurchaseOrderNotFound::class);

        $this->purchaseOrders()->save($orphan);
    }

    #[Test]
    #[TestDox('openByVendor() returns only Sent/PartiallyReceived POs for a vendor.')]
    public function open_by_vendor(): void
    {
        // PO_A: Sent — must be returned.
        $a = $this->makeDraft(self::PO_A, self::VENDOR_A, self::FACILITY_MAIN, [[self::LINE_A1, 5, 100]]);
        $a->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock());
        $this->purchaseOrders()->add($a);

        // PO_B: Draft — must be excluded.
        $b = $this->makeDraft(self::PO_B, self::VENDOR_A, self::FACILITY_MAIN, [[self::LINE_B1, 5, 100]]);
        $this->purchaseOrders()->add($b);

        // PO_C: Sent but different vendor — must be excluded.
        $c = $this->makeDraft(self::PO_C, self::VENDOR_B, self::FACILITY_MAIN, [[self::LINE_C1, 5, 100]]);
        $c->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock());
        $this->purchaseOrders()->add($c);

        // PO_D: PartiallyReceived for Vendor A — must also be returned alongside PO_A.
        $partialId = '019571bf-5d51-7000-b500-000000000c04';
        $partialLine = '019571bf-5d51-7000-b500-000000000fd1';
        $d = $this->makeDraft($partialId, self::VENDOR_A, self::FACILITY_MAIN, [[$partialLine, 5, 100]]);
        $d->send(new DateTimeImmutable('2026-05-26 11:00:00'), null, $this->clock());
        $d->receiveLine(
            PurchaseOrderLineId::fromString($partialLine),
            Quantity::ofUnits(2),
            new DateTimeImmutable('2026-05-26 12:00:00'),
            $this->clock(),
        );
        $this->purchaseOrders()->add($d);

        $open = $this->purchaseOrders()->openByVendor(VendorId::fromString(self::VENDOR_A), 0, 10);

        self::assertCount(2, $open);
        $ids = array_map(static fn ($po) => $po->id()->value, $open);
        self::assertContains(self::PO_A, $ids);
        self::assertContains($partialId, $ids);
    }

    #[Test]
    #[TestDox('byStatus() paginates deterministically by createdAt DESC then id ASC.')]
    public function by_status_paginates(): void
    {
        $a = $this->makeDraft(self::PO_A, self::VENDOR_A, self::FACILITY_MAIN, [[self::LINE_A1, 1, 100]]);
        $this->purchaseOrders()->add($a);

        $this->clock()->modify('+1 hour');

        $b = $this->makeDraft(self::PO_B, self::VENDOR_A, self::FACILITY_MAIN, [[self::LINE_B1, 1, 100]]);
        $this->purchaseOrders()->add($b);

        // PO_C shares PO_B's createdAt instant; the id-ASC tie-break must put PO_B before PO_C.
        $c = $this->makeDraft(self::PO_C, self::VENDOR_A, self::FACILITY_MAIN, [[self::LINE_C1, 1, 100]]);
        $this->purchaseOrders()->add($c);

        $rows = $this->purchaseOrders()->byStatus(PurchaseOrderStatus::Draft, 0, 10);

        self::assertCount(3, $rows);
        self::assertSame(self::PO_B, $rows[0]->id()->value, 'newer createdAt + earlier id wins');
        self::assertSame(self::PO_C, $rows[1]->id()->value, 'tie-break by id ASC at same createdAt');
        self::assertSame(self::PO_A, $rows[2]->id()->value, 'older createdAt last');
    }

    #[Test]
    #[TestDox('byFacility() filters POs by their facility_code.')]
    public function by_facility(): void
    {
        $a = $this->makeDraft(self::PO_A, self::VENDOR_A, self::FACILITY_MAIN, [[self::LINE_A1, 1, 100]]);
        $this->purchaseOrders()->add($a);

        $b = $this->makeDraft(self::PO_B, self::VENDOR_A, self::FACILITY_LAKE, [[self::LINE_B1, 1, 100]]);
        $this->purchaseOrders()->add($b);

        $main = $this->purchaseOrders()->byFacility(FacilityCode::fromString(self::FACILITY_MAIN), 0, 10);
        $lake = $this->purchaseOrders()->byFacility(FacilityCode::fromString(self::FACILITY_LAKE), 0, 10);

        self::assertCount(1, $main);
        self::assertSame(self::PO_A, $main[0]->id()->value);
        self::assertCount(1, $lake);
        self::assertSame(self::PO_B, $lake[0]->id()->value);
    }

    /**
     * @param list<array{string, int, int}> $lineSpecs
     */
    private function makeDraft(string $poId, string $vendorId, string $facility, array $lineSpecs): PurchaseOrder
    {
        $lines = [];
        foreach ($lineSpecs as $spec) {
            [$lineId, $qty, $costCents] = $spec;
            $lines[] = new PurchaseOrderLineDraft(
                PurchaseOrderLineId::fromString($lineId),
                InventoryItemId::fromString(self::ITEM_A),
                Quantity::ofUnits($qty),
                CostPerUnit::ofCents($costCents),
            );
        }

        return PurchaseOrder::createDraft(
            PurchaseOrderId::fromString($poId),
            VendorId::fromString($vendorId),
            FacilityCode::fromString($facility),
            $lines,
            $this->clock(),
        );
    }
}
