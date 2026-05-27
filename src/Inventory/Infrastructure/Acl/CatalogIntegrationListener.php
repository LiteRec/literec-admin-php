<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Acl;

use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Integration\Event\LineSold;
use App\Inventory\Domain\Combos;
use App\Inventory\Domain\Event\StockMovementRecorded;
use App\Inventory\Domain\Exception\ComboNotFound;
use App\Inventory\Domain\Exception\InsufficientStock;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ItemLinks;
use App\Inventory\Domain\StockMovementLedger;
use App\Inventory\Domain\ValueObject\CostPerUnit;
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
 * Idempotency: a duplicate envelope (Messenger at-least-once redelivery)
 * is short-circuited two ways. First, before any consume, the listener
 * probes {@see StockMovementLedger::hasConsumedFor()} against every
 * (transactionId, componentItemId, facilityCode) tuple and returns
 * without writing on a hit. Second, after each successful consume, the
 * listener appends a CONSUMED row to the ledger via
 * {@see StockMovementLedger::recordConsumed()} — the partial UNIQUE
 * index on (transaction_id, item_id, facility_code) in the
 * inventory_stock_movements table is the second-line-of-defence
 * dedupe should the pre-flight guard race a concurrent redelivery
 * (LRA-94).
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
        private readonly StockMovementLedger $movementLedger,
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

        // Idempotency guard: if the ledger already carries a CONSUMED
        // row for any of these (transaction_id, item_id, facility_code)
        // tuples, the envelope has already been processed (Messenger
        // at-least-once redelivery). Short-circuit before re-consuming.
        foreach ($componentTuples as [$componentId]) {
            if (
                $this->movementLedger->hasConsumedFor(
                    $event->transactionId,
                    $event->listingId,
                    $componentId,
                    $facility,
                )
            ) {
                $this->logger->info('Inventory ACL: duplicate LineSold envelope ignored.', [
                    'transaction_id' => $event->transactionId,
                    'inventory_item_id' => $componentId->value,
                    'facility_code' => $event->facilityCode,
                ]);
                return;
            }
        }

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

                $releasedEvents = $itemToMutate->releaseEvents();

                // Append the CONSUMED summary row carrying the transaction
                // id so the partial unique index serves as the second-
                // line-of-defence dedupe should the pre-flight guard
                // race with a concurrent redelivery. cost_per_unit_cents
                // is the weighted average across every StockBatch the
                // FIFO walk touched — preserves the per-component basis
                // even though the summary row carries no stock_batch_id
                // (the consume may span multiple batches; per-batch
                // detail lives in the stock_batches join).
                $this->movementLedger->recordConsumed(
                    itemId: $componentId,
                    facilityCode: $facility,
                    stockBatchId: null,
                    reason: StockMovementReason::SALE,
                    quantity: $componentQty,
                    costPerUnit: $this->weightedAverageCost($releasedEvents, $componentQty),
                    transactionId: $event->transactionId,
                    listingId: $event->listingId,
                    recordedAt: $this->clock->now(),
                );

                foreach ($releasedEvents as $domainEvent) {
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

    /**
     * Weighted-average cost across every StockMovementRecorded event the
     * consume call released — preserves the cost basis on the CONSUMED
     * summary row even though the row carries no stock_batch_id (the
     * consume may span multiple FIFO batches).
     *
     * Throws when the released-events list does not cover the full
     * requested quantity: a missing or partial set would produce a
     * silently-wrong cost_per_unit_cents on the ledger row, masking an
     * aggregate invariant break. Every consume must emit one
     * StockMovementRecorded per batch touched whose quantities sum to
     * the requested amount.
     *
     * @param list<object> $releasedEvents
     */
    private function weightedAverageCost(array $releasedEvents, Quantity $totalQuantity): CostPerUnit
    {
        if ($totalQuantity->isZero()) {
            return CostPerUnit::zero();
        }

        $weightedCents = 0;
        $countedUnits = 0;

        foreach ($releasedEvents as $domainEvent) {
            if (! $domainEvent instanceof StockMovementRecorded) {
                continue;
            }
            $weightedCents += $domainEvent->costPerUnit->cents * $domainEvent->quantityConsumed->units;
            $countedUnits += $domainEvent->quantityConsumed->units;
        }

        if ($countedUnits !== $totalQuantity->units) {
            throw new \LogicException(sprintf(
                'StockMovementRecorded events covered %d unit(s); expected %d to match the consume request.',
                $countedUnits,
                $totalQuantity->units,
            ));
        }

        return CostPerUnit::ofCents(intdiv($weightedCents, $countedUnits));
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
