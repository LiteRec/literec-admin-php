<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

use App\Inventory\Application\Query\View\FacilityStockBlockView;
use App\Inventory\Application\Query\View\InventoryItemDetailView;
use App\Inventory\Application\Query\View\StockBatchView;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\StockBatch;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Read-side handler: projects an InventoryItem aggregate into a flat
 * view DTO with per-facility blocks of FIFO-ordered StockBatch
 * projections.
 *
 * Implementation note: this version projects from the live aggregate
 * via the InventoryItems port to keep the read path independent of any
 * specific persistence layer. When the production Doctrine adapter
 * lands (LRA-76 follow-up) a SELECT NEW DQL projection can replace the
 * aggregate-to-view assembly without touching the handler's contract.
 */
#[AsMessageHandler(bus: 'query.bus')]
final class GetInventoryItemDetailHandler
{
    public function __construct(
        private readonly InventoryItems $inventoryItems,
    ) {
    }

    public function __invoke(GetInventoryItemDetail $query): InventoryItemDetailView
    {
        $item = $this->inventoryItems->byId(InventoryItemId::fromString($query->inventoryItemId));

        // Group sorted batches by facility, preserving FIFO order inside
        // each facility (sortedBatches() returns FIFO across all
        // facilities; iteration order produces the same FIFO order per
        // facility key).
        /** @var array<string, list<StockBatch>> $byFacility */
        $byFacility = [];
        foreach ($item->batches() as $batch) {
            $byFacility[$batch->facilityCode()->value][] = $batch;
        }
        ksort($byFacility);

        $blocks = [];
        foreach ($byFacility as $facilityCode => $batches) {
            $totalOnHand = 0;
            $batchViews = [];
            foreach ($batches as $batch) {
                $totalOnHand += $batch->remainingQuantity()->units;
                $batchViews[] = new StockBatchView(
                    stockBatchId: $batch->id()->value,
                    originalQuantityUnits: $batch->originalQuantity()->units,
                    remainingQuantityUnits: $batch->remainingQuantity()->units,
                    costPerUnitCents: $batch->costPerUnit()->cents,
                    sourceLineId: $batch->sourceLineId()?->value,
                    comment: $batch->comments()?->value,
                    receivedAt: $batch->receivedAt(),
                );
            }
            $blocks[] = new FacilityStockBlockView(
                facilityCode: $facilityCode,
                totalOnHandUnits: $totalOnHand,
                batches: $batchViews,
            );
        }

        return new InventoryItemDetailView(
            inventoryItemId: $item->id()->value,
            listingId: $item->listingId()->value,
            primaryVendorId: $item->primaryVendorId()?->value,
            posColorHex: $item->posColor()->hex,
            tracksInventory: $item->tracksInventory(),
            rentable: $item->isRentable(),
            reorderThresholdUnits: $item->reorderThreshold()->units,
            archived: $item->isArchived(),
            totalOnHandUnits: $item->totalOnHand()->units,
            facilityStockBlocks: $blocks,
            registeredAt: $item->registeredAt(),
            updatedAt: $item->updatedAt(),
        );
    }
}
