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
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use DateTimeImmutable;
use Symfony\Component\Clock\MockClock;

/**
 * Shared seed helper for the LRA-86/87 Inventory UI functional tests.
 * Each consumer ships its own ID constants so seeded rows stay
 * isolated across the suite; this trait just collapses the
 * Listing + InventoryItem + StockBatch construction boilerplate.
 *
 * Lives under tests/Support so deptrac does not pull it into the
 * production graph.
 */
trait SeedsInventoryItemForUi
{
    private function seedInventoryItemWithStock(
        string $itemId,
        string $listingId,
        string $batchId,
        string $listingCode,
        string $listingName,
        string $facilityCode,
        int $initialQuantityUnits = 5,
        int $costPerUnitCents = 100,
    ): void {
        $listings = static::getContainer()->get(Listings::class);
        self::assertInstanceOf(Listings::class, $listings);
        $items = static::getContainer()->get(InventoryItems::class);
        self::assertInstanceOf(InventoryItems::class, $items);

        $clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));

        $listing = Listing::register(
            ListingId::fromString($listingId),
            ListingCode::of($listingCode),
            ListingKind::Inventory,
            $listingName,
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
        $item->receiveBatch(
            FacilityCode::fromString($facilityCode),
            Quantity::ofUnits($initialQuantityUnits),
            CostPerUnit::ofCents($costPerUnitCents),
            null,
            null,
            StockBatchId::fromString($batchId),
            $clock,
        );
        $item->releaseEvents();
        $items->add($item);
    }
}
