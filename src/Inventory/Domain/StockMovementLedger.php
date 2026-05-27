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
     * Record a consume row (kind=CONSUMED) tied to the originating
     * LineSold envelope. The idempotency key is the four-tuple
     * (transaction_id, listing_id, item_id, facility_code) — keying
     * only on (transaction_id, item_id) would incorrectly skip
     * legitimate sibling lines that touch the same component.
     *
     * Implementations MUST let a unique-constraint violation on the
     * dedupe index propagate so the surrounding doctrine_transaction
     * middleware rolls back the matching consume — silently swallowing
     * the violation would leave the stock decremented twice under a
     * redelivery race. Callers that need an idempotent
     * "already-consumed" check should consult {@see hasConsumedFor()}
     * BEFORE the consume.
     */
    public function recordConsumed(
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        ?StockBatchId $stockBatchId,
        StockMovementReason $reason,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        string $transactionId,
        string $listingId,
        DateTimeImmutable $recordedAt,
        ?string $operatorNote = null,
    ): void;

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
     * Keyed on the same four-tuple as the dedupe index so the probe
     * matches what {@see recordConsumed()} enforces.
     */
    public function hasConsumedFor(
        string $transactionId,
        string $listingId,
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
    ): bool;
}
