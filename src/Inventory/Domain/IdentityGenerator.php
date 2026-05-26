<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementId;
use App\Inventory\Domain\ValueObject\VendorId;

/**
 * Domain port for generating Inventory aggregate and entity identities.
 *
 * Implementations live in Infrastructure and produce UUID v7 values so
 * identifiers are time-ordered and safe to use as primary keys before
 * the aggregate is persisted.
 */
interface IdentityGenerator
{
    public function nextInventoryItemId(): InventoryItemId;

    public function nextStockBatchId(): StockBatchId;

    public function nextStockMovementId(): StockMovementId;

    public function nextVendorId(): VendorId;

    public function nextPurchaseOrderId(): PurchaseOrderId;

    public function nextPurchaseOrderLineId(): PurchaseOrderLineId;
}
