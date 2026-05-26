<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryInventoryItems;
use Psr\Clock\ClockInterface;

/**
 * Seeds an in-memory {@see InventoryItem} pre-loaded with a single
 * {@see StockBatch} at the requested facility, releases the registration
 * and receipt events, and registers the aggregate with the in-memory
 * repository. Used by application-service handler tests that need a
 * fresh aggregate to dispatch their command against.
 */
trait SeedsInventoryItemWithBatch
{
    private function seedItemWithBatch(
        InMemoryInventoryItems $items,
        ClockInterface $clock,
        string $itemId,
        string $listingId,
        string $facility,
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
            $clock,
        );
        $item->receiveBatch(
            FacilityCode::fromString($facility),
            Quantity::ofUnits($units),
            CostPerUnit::ofCents($costCents),
            null,
            null,
            StockBatchId::fromString($batchId),
            $clock,
        );
        $item->releaseEvents();
        $items->add($item);
    }
}
