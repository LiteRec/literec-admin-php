<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Catalog\Domain\Listing;
use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\Listings;
use App\Catalog\Domain\ValueObject\LedgerAccount;
use App\Catalog\Domain\ValueObject\ListingCode;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Domain\ValueObject\TaxTreatment;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\PurchaseOrder;
use App\Inventory\Domain\PurchaseOrders;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineDraft;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\VendorCode;
use App\Inventory\Domain\ValueObject\VendorContact;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Domain\ValueObject\VendorName;
use App\Inventory\Domain\Vendor;
use App\Inventory\Domain\Vendors;
use DateTimeImmutable;
use Symfony\Component\Clock\MockClock;

/**
 * Functional-test seed helper for LRA-90 PO pages: builds the
 * cross-aggregate prerequisites (Vendor + Listing + InventoryItem)
 * then drafts a PurchaseOrder with the supplied lines.
 *
 * Lives under tests/Support so deptrac does not pull it into the
 * production graph.
 */
trait SeedsPurchaseOrderForUi
{
    /**
     * @param list<array{lineId: string, orderedUnits: int, costPerUnitCents: int}> $lineSpecs
     */
    private function seedDraftPurchaseOrder(
        string $poId,
        string $vendorId,
        string $vendorCode,
        string $listingId,
        string $listingCode,
        string $itemId,
        string $facilityCode,
        array $lineSpecs,
    ): void {
        $clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));

        $vendors = static::getContainer()->get(Vendors::class);
        self::assertInstanceOf(Vendors::class, $vendors);
        $listings = static::getContainer()->get(Listings::class);
        self::assertInstanceOf(Listings::class, $listings);
        $items = static::getContainer()->get(InventoryItems::class);
        self::assertInstanceOf(InventoryItems::class, $items);
        $orders = static::getContainer()->get(PurchaseOrders::class);
        self::assertInstanceOf(PurchaseOrders::class, $orders);

        $vendor = Vendor::register(
            VendorId::fromString($vendorId),
            VendorCode::fromString($vendorCode),
            VendorName::of('Acme Suppliers'),
            VendorContact::of('Jane Doe'),
            null,
            null,
            null,
            $clock,
        );
        $vendor->releaseEvents();
        $vendors->add($vendor);

        $listing = Listing::register(
            ListingId::fromString($listingId),
            ListingCode::of($listingCode),
            ListingKind::Inventory,
            'Seeded PO Item',
            [],
            TaxTreatment::none(),
            LedgerAccount::of('4000'),
            $clock,
        );
        $listing->releaseEvents();
        $listings->add($listing);

        $item = InventoryItem::register(
            InventoryItemId::fromString($itemId),
            ListingId::fromString($listingId),
            null,
            PosColor::default(),
            true,
            false,
            ReorderThreshold::ofUnits(0),
            $clock,
        );
        $item->releaseEvents();
        $items->add($item);

        $lineDrafts = [];
        foreach ($lineSpecs as $spec) {
            $lineDrafts[] = new PurchaseOrderLineDraft(
                PurchaseOrderLineId::fromString($spec['lineId']),
                InventoryItemId::fromString($itemId),
                Quantity::ofUnits($spec['orderedUnits']),
                CostPerUnit::ofCents($spec['costPerUnitCents']),
            );
        }

        $po = PurchaseOrder::createDraft(
            PurchaseOrderId::fromString($poId),
            VendorId::fromString($vendorId),
            FacilityCode::fromString($facilityCode),
            $lineDrafts,
            $clock,
        );
        $po->releaseEvents();
        $orders->add($po);
    }
}
