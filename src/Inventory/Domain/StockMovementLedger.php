<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use DateTimeImmutable;

/**
 * Domain port for the append-only inventory stock movements ledger.
 *
 * Owns the dedupe guard the LRA-83 ACL relies on plus the per-kind
 * append helpers used by the post-commit event subscribers and the
 * Take-Inventory handler. The ledger is write-only through this port;
 * read paths go through the LRA-97 query handlers, never through here.
 *
 * Lives in Domain because both the Application layer (AdjustStockHandler
 * writes ADJUSTED rows inline) and the Infrastructure layer (ACL +
 * subscribers) need it; placing the interface in Domain is the only
 * way to satisfy the dependency-inversion rule from both sides without
 * Application importing Infrastructure.
 */
interface StockMovementLedger
{
    /**
     * Record a consume row (kind=CONSUMED) tied to a transaction id.
     *
     * Returns true when the row was inserted, false when the unique
     * constraint on (transaction_id, item_id, facility_code) blocked
     * the insert (the second-line-of-defence idempotency path).
     */
    public function recordConsumed(
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        ?StockBatchId $stockBatchId,
        StockMovementReason $reason,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        string $transactionId,
        DateTimeImmutable $recordedAt,
        ?string $operatorNote = null,
    ): bool;

    public function recordReceived(
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        StockBatchId $stockBatchId,
        StockMovementReason $reason,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        DateTimeImmutable $recordedAt,
        ?string $operatorNote = null,
    ): void;

    public function recordReturned(
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        StockBatchId $stockBatchId,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        DateTimeImmutable $recordedAt,
    ): void;

    public function recordTransferredOut(
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        StockBatchId $stockBatchId,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        DateTimeImmutable $recordedAt,
    ): void;

    public function recordTransferredIn(
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        StockBatchId $stockBatchId,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        DateTimeImmutable $recordedAt,
    ): void;

    public function recordAdjusted(
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        ?StockBatchId $stockBatchId,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        DateTimeImmutable $recordedAt,
        ?string $operatorNote = null,
    ): void;

    /**
     * Probe used by the LRA-83 ACL idempotency guard: returns true when
     * the ledger already contains a row for the given consume tuple.
     */
    public function hasConsumedFor(
        string $transactionId,
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
    ): bool;
}
