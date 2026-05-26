<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementId;
use App\Inventory\Domain\ValueObject\VendorId;
use LogicException;

/**
 * Test double for the Inventory IdentityGenerator port. Each accessor
 * returns the next entry from its own queue; an exhausted queue throws
 * so a test that accidentally requests more ids than it set up fails
 * loudly.
 *
 * Tests construct a single instance and seed only the queues for the
 * identity flavors they exercise; flavors they do not need stay empty
 * and surface as LogicException if reached.
 */
final class SequenceInventoryIdentityGenerator implements IdentityGenerator
{
    /** @var list<InventoryItemId> */
    private array $inventoryItemQueue;

    /** @var list<StockBatchId> */
    private array $stockBatchQueue;

    /** @var list<StockMovementId> */
    private array $stockMovementQueue;

    /** @var list<VendorId> */
    private array $vendorQueue;

    /** @var list<PurchaseOrderId> */
    private array $purchaseOrderQueue;

    /** @var list<PurchaseOrderLineId> */
    private array $purchaseOrderLineQueue;

    /** @var list<ComboId> */
    private array $comboQueue;

    /** @var list<ItemGroupId> */
    private array $itemGroupQueue;

    /** @var list<ItemLinkId> */
    private array $itemLinkQueue;

    /**
     * @param list<InventoryItemId>    $inventoryItemIds
     * @param list<StockBatchId>       $stockBatchIds
     * @param list<StockMovementId>    $stockMovementIds
     * @param list<VendorId>           $vendorIds
     * @param list<PurchaseOrderId>    $purchaseOrderIds
     * @param list<PurchaseOrderLineId> $purchaseOrderLineIds
     * @param list<ComboId>            $comboIds
     * @param list<ItemGroupId>        $itemGroupIds
     * @param list<ItemLinkId>         $itemLinkIds
     */
    public function __construct(
        array $inventoryItemIds = [],
        array $stockBatchIds = [],
        array $stockMovementIds = [],
        array $vendorIds = [],
        array $purchaseOrderIds = [],
        array $purchaseOrderLineIds = [],
        array $comboIds = [],
        array $itemGroupIds = [],
        array $itemLinkIds = [],
    ) {
        $this->inventoryItemQueue = $inventoryItemIds;
        $this->stockBatchQueue = $stockBatchIds;
        $this->stockMovementQueue = $stockMovementIds;
        $this->vendorQueue = $vendorIds;
        $this->purchaseOrderQueue = $purchaseOrderIds;
        $this->purchaseOrderLineQueue = $purchaseOrderLineIds;
        $this->comboQueue = $comboIds;
        $this->itemGroupQueue = $itemGroupIds;
        $this->itemLinkQueue = $itemLinkIds;
    }

    public function nextInventoryItemId(): InventoryItemId
    {
        return self::shift($this->inventoryItemQueue, 'InventoryItemId');
    }

    public function nextStockBatchId(): StockBatchId
    {
        return self::shift($this->stockBatchQueue, 'StockBatchId');
    }

    public function nextStockMovementId(): StockMovementId
    {
        return self::shift($this->stockMovementQueue, 'StockMovementId');
    }

    public function nextVendorId(): VendorId
    {
        return self::shift($this->vendorQueue, 'VendorId');
    }

    public function nextPurchaseOrderId(): PurchaseOrderId
    {
        return self::shift($this->purchaseOrderQueue, 'PurchaseOrderId');
    }

    public function nextPurchaseOrderLineId(): PurchaseOrderLineId
    {
        return self::shift($this->purchaseOrderLineQueue, 'PurchaseOrderLineId');
    }

    public function nextComboId(): ComboId
    {
        return self::shift($this->comboQueue, 'ComboId');
    }

    public function nextItemGroupId(): ItemGroupId
    {
        return self::shift($this->itemGroupQueue, 'ItemGroupId');
    }

    public function nextItemLinkId(): ItemLinkId
    {
        return self::shift($this->itemLinkQueue, 'ItemLinkId');
    }

    /**
     * @template T of object
     * @param  list<T> $queue
     * @return T
     */
    private static function shift(array &$queue, string $flavor): object
    {
        if ($queue === []) {
            throw new LogicException(sprintf('%s identity queue exhausted.', $flavor));
        }

        return array_shift($queue);
    }
}
