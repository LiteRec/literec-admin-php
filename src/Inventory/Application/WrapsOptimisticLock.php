<?php

declare(strict_types=1);

namespace App\Inventory\Application;

use App\Inventory\Domain\Exception\ConcurrentInventoryItemModification;
use App\Inventory\Domain\Exception\ConcurrentPurchaseOrderModification;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use Closure;
use Doctrine\ORM\OptimisticLockException;

/**
 * Shared LRA-99 helper for Inventory application-service handlers
 * that persist a versioned aggregate. Wraps a repository save closure
 * in a try/catch that translates the raw Doctrine
 * {@see OptimisticLockException} into the appropriate named domain
 * exception. Controllers can then catch
 * {@see \App\Inventory\Domain\Exception\ConcurrentModification} (or
 * its concrete subtypes) and map the race to HTTP 409 without
 * importing any Doctrine types.
 *
 * Each handler picks the wrap method that matches its aggregate.
 * {@see \App\Inventory\Application\Command\ReceivePurchaseOrderLineHandler}
 * mutates both aggregates in one transaction and therefore calls
 * both wrapPurchaseOrderLineSave and wrapInventoryItemSave — the
 * doctrine_transaction middleware wraps the whole envelope so a
 * race on either save rolls back both writes.
 */
trait WrapsOptimisticLock
{
    /**
     * @template T
     * @param Closure(): T $persist
     * @return T
     */
    private function wrapInventoryItemSave(InventoryItemId $itemId, Closure $persist): mixed
    {
        try {
            return $persist();
        } catch (OptimisticLockException $e) {
            throw ConcurrentInventoryItemModification::forItem($itemId, $e);
        }
    }

    /**
     * @template T
     * @param Closure(): T $persist
     * @return T
     */
    private function wrapPurchaseOrderSave(PurchaseOrderId $purchaseOrderId, Closure $persist): mixed
    {
        try {
            return $persist();
        } catch (OptimisticLockException $e) {
            throw ConcurrentPurchaseOrderModification::forPurchaseOrder($purchaseOrderId, $e);
        }
    }

    /**
     * @template T
     * @param Closure(): T $persist
     * @return T
     */
    private function wrapPurchaseOrderLineSave(
        PurchaseOrderId $purchaseOrderId,
        PurchaseOrderLineId $lineId,
        Closure $persist,
    ): mixed {
        try {
            return $persist();
        } catch (OptimisticLockException $e) {
            throw ConcurrentPurchaseOrderModification::forLine($purchaseOrderId, $lineId, $e);
        }
    }
}
