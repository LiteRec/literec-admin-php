<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Acl;

use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Integration\Event\LineSold;
use App\Inventory\Domain\Combos;
use App\Inventory\Domain\Exception\ComboNotFound;
use App\Inventory\Domain\Exception\InsufficientStock;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ItemLinks;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use App\Inventory\Integration\Event\StockConsumptionFailed;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Anti-Corruption Layer: subscribes to Catalog's
 * {@see App\Catalog\Integration\Event\LineSold} integration event and
 * translates a sale into Inventory domain calls — FIFO consumption with
 * combo expansion and item-link enforcement.
 *
 * Sole class in `src/Inventory/` allowed to import a Catalog type beyond
 * the published-language `ListingId` / `ListingKind` value objects
 * (enforced by Deptrac — `InventoryInfrastructure` lists
 * `CatalogIntegration` as an allowed dependency).
 *
 * Flow:
 *  1. Short-circuit when {@see LineSold::$listingKind} is not 'INVENTORY'.
 *  2. Resolve the InventoryItem via {@see InventoryItems::byListingId()}.
 *     Missing → emit {@see StockConsumptionFailed} with
 *     {@see StockConsumptionFailed::REASON_UNKNOWN_INVENTORY_ITEM}.
 *  3. Expand combos to their components (component itemId × quantity per
 *     combo); atomic items expand to themselves.
 *  4. For each resolved component, walk the active item-links at the
 *     transaction's facility and call
 *     {@see App\Inventory\Domain\ItemLink::wouldViolateAt()}. Any
 *     violation cancels the whole consumption and emits a
 *     {@see StockConsumptionFailed} with the offending link id.
 *  5. Consume FIFO from each component via
 *     {@see InventoryItem::consume()} with reason {@see StockMovementReason::SALE}.
 *     {@see InsufficientStock} rolls back the doctrine_transaction
 *     middleware around this handler and a follow-up
 *     {@see StockConsumptionFailed} fires with reason
 *     {@see StockConsumptionFailed::REASON_INSUFFICIENT_STOCK}.
 *
 * Idempotency note: the plan calls for a transactional dedupe on the
 * inventory_stock_movements ledger (unique key on transactionId +
 * itemId + facility). That table + its writer lands in a focused
 * follow-up paired with the LRA-76 schema. Until then the ACL relies on
 * Messenger's standard at-least-once semantics; downstream consumers of
 * StockConsumptionFailed already dedupe on (transactionId, listingId).
 */
#[AsMessageHandler]
final class CatalogIntegrationListener
{
    public function __construct(
        private readonly InventoryItems $inventoryItems,
        private readonly Combos $combos,
        private readonly ItemLinks $itemLinks,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(LineSold $event): void
    {
        if ($event->listingKind !== ListingKind::Inventory->value) {
            return;
        }

        $listingId = ListingId::fromString($event->listingId);
        $facility = FacilityCode::fromString($event->facilityCode);
        $saleQuantity = Quantity::ofUnits($event->quantity);

        try {
            $masterItem = $this->inventoryItems->byListingId($listingId);
        } catch (InventoryItemNotFound) {
            $this->logger->error('Inventory ACL: unknown inventory item for LineSold listing.', [
                'listing_id' => $event->listingId,
                'transaction_id' => $event->transactionId,
            ]);
            $this->dispatchFailure(
                $event,
                StockConsumptionFailed::REASON_UNKNOWN_INVENTORY_ITEM,
                offendingInventoryItemId: null,
                offendingLinkId: null,
            );
            return;
        }

        $componentTuples = $this->expandCombo($masterItem, $saleQuantity);

        // Pre-flight: walk every active item link for the components and
        // bail out before mutating stock if any constraint blocks the sale.
        foreach ($componentTuples as [$componentId, $componentQty]) {
            $violation = $this->firstLinkViolation(
                $componentId,
                $componentQty,
                $facility,
                $event->occurredAt,
            );
            if ($violation !== null) {
                $this->logger->warning('Inventory ACL: item link blocks consumption.', [
                    'transaction_id' => $event->transactionId,
                    'inventory_item_id' => $componentId->value,
                    'link_id' => $violation['linkId'],
                    'violation' => $violation['reason'],
                ]);
                $this->dispatchFailure(
                    $event,
                    StockConsumptionFailed::REASON_LINK_VIOLATION,
                    offendingInventoryItemId: $componentId->value,
                    offendingLinkId: $violation['linkId'],
                );
                return;
            }
        }

        // Consume FIFO from each component. Failures rethrow after we
        // emit the StockConsumptionFailed envelope so the wrapping
        // doctrine_transaction middleware rolls back the partial writes.
        try {
            foreach ($componentTuples as [$componentId, $componentQty]) {
                $itemToMutate = $componentId->equals($masterItem->id())
                    ? $masterItem
                    : $this->inventoryItems->byId($componentId);

                $itemToMutate->consume($facility, $componentQty, StockMovementReason::SALE, $this->clock);
                $this->inventoryItems->save($itemToMutate);

                foreach ($itemToMutate->releaseEvents() as $domainEvent) {
                    $this->eventBus->dispatch($domainEvent, [new DispatchAfterCurrentBusStamp()]);
                }
            }
        } catch (InsufficientStock $e) {
            $this->logger->warning('Inventory ACL: insufficient stock to satisfy LineSold.', [
                'transaction_id' => $event->transactionId,
                'inventory_item_id' => $e->inventoryItemId->value,
                'requested' => $e->requested->units,
                'available' => $e->available->units,
            ]);
            $this->dispatchFailure(
                $event,
                StockConsumptionFailed::REASON_INSUFFICIENT_STOCK,
                offendingInventoryItemId: $e->inventoryItemId->value,
                offendingLinkId: null,
            );
            throw $e;
        }

        $this->logger->info('Inventory ACL: consumed stock for LineSold.', [
            'transaction_id' => $event->transactionId,
            'listing_id' => $event->listingId,
            'components' => count($componentTuples),
        ]);
    }

    /**
     * @return list<array{0: InventoryItemId, 1: Quantity}>
     */
    private function expandCombo(InventoryItem $masterItem, Quantity $saleQuantity): array
    {
        try {
            $combo = $this->combos->byListingId($masterItem->listingId());
        } catch (ComboNotFound) {
            return [[$masterItem->id(), $saleQuantity]];
        }

        $tuples = [];
        foreach ($combo->components() as $component) {
            $tuples[] = [
                $component->componentItemId,
                Quantity::ofUnits($component->quantityPerCombo->units * $saleQuantity->units),
            ];
        }
        return $tuples;
    }

    /**
     * @return array{linkId: string, reason: string}|null
     */
    private function firstLinkViolation(
        InventoryItemId $masterComponentId,
        Quantity $componentQty,
        FacilityCode $facility,
        \DateTimeImmutable $now,
    ): ?array {
        $activeLinks = $this->itemLinks->activeForMaster($masterComponentId, $now);
        if ($activeLinks === []) {
            return null;
        }

        foreach ($activeLinks as $link) {
            try {
                $linkedItem = $this->inventoryItems->byId($link->linkedItemId());
            } catch (InventoryItemNotFound) {
                // The link references an inventory item that has since
                // been removed. Log a warning so an admin can clean it
                // up, then skip enforcement for this dangling link.
                $this->logger->warning('Inventory ACL: item link points at a missing inventory item.', [
                    'link_id' => $link->id()->value,
                    'master_item_id' => $link->masterItemId()->value,
                    'linked_item_id' => $link->linkedItemId()->value,
                ]);
                continue;
            }

            $stockOnHand = $linkedItem->totalOnHandAt($facility);

            // The LineSold envelope describes one line — the master
            // component being sold. The ACL has no visibility into
            // sibling lines that may also touch the linked item in the
            // same transaction, so linkedQtyInThisPurchase is 0 here.
            // The reserved-quantity floor and min/max checks evaluate
            // against the master quantity and current on-hand stock.
            // (LRA-92 fixtures stress this single-line shape; richer
            // multi-line awareness lands when the Transactions context
            // ships and can supply a bundled "lines in this purchase"
            // envelope.)
            $violation = $link->wouldViolateAt(
                $now,
                $componentQty,
                $stockOnHand,
                Quantity::zero(),
                Quantity::zero(),
            );
            if ($violation !== null) {
                return ['linkId' => $link->id()->value, 'reason' => $violation->value];
            }
        }

        return null;
    }

    private function dispatchFailure(
        LineSold $event,
        string $reasonCode,
        ?string $offendingInventoryItemId,
        ?string $offendingLinkId,
    ): void {
        $this->eventBus->dispatch(new StockConsumptionFailed(
            listingId: $event->listingId,
            transactionId: $event->transactionId,
            facilityCode: $event->facilityCode,
            reasonCode: $reasonCode,
            offendingInventoryItemId: $offendingInventoryItemId,
            offendingLinkId: $offendingLinkId,
            occurredAt: $this->clock->now(),
        ));
    }
}
