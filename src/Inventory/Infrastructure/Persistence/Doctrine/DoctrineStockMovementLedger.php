<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\StockMovementLedger;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\StockAdjustmentDirection;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementId;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;

/**
 * Append-only writer for the inventory_stock_movements ledger.
 *
 * Plain DBAL — no ORM entity, no UnitOfWork. The ledger is a fire-and-
 * forget projection target for stock events; rows are never updated
 * after insert and never read back through this writer (LRA-97
 * GetStockMovementHistory owns the read path).
 *
 * Idempotency is enforced at the schema level via the UNIQUE PARTIAL
 * index on (transaction_id, item_id, facility_code) WHERE
 * transaction_id IS NOT NULL. The {@see recordConsumed()} path lets a
 * {@see \Doctrine\DBAL\Exception\UniqueConstraintViolationException}
 * propagate so the surrounding doctrine_transaction middleware rolls
 * back the matching consume — silently swallowing the violation would
 * leave stock decremented twice under a redelivery race. Callers that
 * need an idempotent "already-consumed" check consult
 * {@see hasConsumedFor()} BEFORE the consume. Non-consume paths
 * (receive, return, transfer, adjust) carry a null transaction_id
 * and never trip the partial index — those inserts always succeed.
 */
final readonly class DoctrineStockMovementLedger implements StockMovementLedger
{
    public function __construct(
        private Connection $connection,
        private IdentityGenerator $identityGenerator,
    ) {
    }

    /**
     * Record a consume row (kind=CONSUMED) tied to the originating
     * LineSold envelope (transaction_id + listing_id pair).
     *
     * A unique-constraint violation on the four-tuple dedupe index
     * (transaction_id, listing_id, item_id, facility_code) is
     * intentionally allowed to propagate so the surrounding
     * doctrine_transaction middleware rolls back the matching
     * consume. Callers MUST probe {@see hasConsumedFor()} before
     * consuming if they need an idempotent fast-path that avoids
     * the rollback cost on a duplicate envelope.
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
    ): void {
        $this->insert(
            kind: 'CONSUMED',
            itemId: $itemId,
            facilityCode: $facilityCode,
            stockBatchId: $stockBatchId,
            reason: $reason,
            quantity: $quantity,
            costPerUnit: $costPerUnit,
            transactionId: $transactionId,
            listingId: $listingId,
            recordedAt: $recordedAt,
            operatorNote: $operatorNote,
        );
    }

    public function recordReceived(
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        StockBatchId $stockBatchId,
        StockMovementReason $reason,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        DateTimeImmutable $recordedAt,
        ?string $operatorNote = null,
    ): void {
        $this->insert(
            kind: 'RECEIVED',
            itemId: $itemId,
            facilityCode: $facilityCode,
            stockBatchId: $stockBatchId,
            reason: $reason,
            quantity: $quantity,
            costPerUnit: $costPerUnit,
            transactionId: null,
            recordedAt: $recordedAt,
            operatorNote: $operatorNote,
        );
    }

    public function recordReturned(
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        StockBatchId $stockBatchId,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        DateTimeImmutable $recordedAt,
    ): void {
        $this->insert(
            kind: 'RETURNED',
            itemId: $itemId,
            facilityCode: $facilityCode,
            stockBatchId: $stockBatchId,
            reason: StockMovementReason::RETURN,
            quantity: $quantity,
            costPerUnit: $costPerUnit,
            transactionId: null,
            recordedAt: $recordedAt,
            operatorNote: null,
        );
    }

    public function recordTransferredOut(
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        StockBatchId $stockBatchId,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        DateTimeImmutable $recordedAt,
    ): void {
        $this->insert(
            kind: 'TRANSFERRED_OUT',
            itemId: $itemId,
            facilityCode: $facilityCode,
            stockBatchId: $stockBatchId,
            reason: StockMovementReason::TRANSFER_OUT,
            quantity: $quantity,
            costPerUnit: $costPerUnit,
            transactionId: null,
            recordedAt: $recordedAt,
            operatorNote: null,
        );
    }

    public function recordTransferredIn(
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        StockBatchId $stockBatchId,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        DateTimeImmutable $recordedAt,
    ): void {
        $this->insert(
            kind: 'TRANSFERRED_IN',
            itemId: $itemId,
            facilityCode: $facilityCode,
            stockBatchId: $stockBatchId,
            reason: StockMovementReason::TRANSFER_IN,
            quantity: $quantity,
            costPerUnit: $costPerUnit,
            transactionId: null,
            recordedAt: $recordedAt,
            operatorNote: null,
        );
    }

    public function recordAdjusted(
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        ?StockBatchId $stockBatchId,
        Quantity $quantity,
        StockAdjustmentDirection $direction,
        CostPerUnit $costPerUnit,
        DateTimeImmutable $recordedAt,
        ?string $operatorNote = null,
    ): void {
        // Direction lands in kind so the ledger row is self-describing
        // without depending on operator_note parsing or a second
        // join: kind ∈ { ADJUSTED_INCREASE, ADJUSTED_DECREASE } stays
        // queryable by LRA-88 (movement history filter) and LRA-91
        // (Entry Log report). reason stays ADJUSTMENT for both
        // directions so the enum dimension remains stable.
        $kind = match ($direction) {
            StockAdjustmentDirection::INCREASE => 'ADJUSTED_INCREASE',
            StockAdjustmentDirection::DECREASE => 'ADJUSTED_DECREASE',
        };

        $this->insert(
            kind: $kind,
            itemId: $itemId,
            facilityCode: $facilityCode,
            stockBatchId: $stockBatchId,
            reason: StockMovementReason::ADJUSTMENT,
            quantity: $quantity,
            costPerUnit: $costPerUnit,
            transactionId: null,
            recordedAt: $recordedAt,
            operatorNote: $operatorNote,
        );
    }

    /**
     * Probe used by the LRA-83 ACL idempotency guard: returns true when
     * the ledger already contains a row for the given consume tuple
     * (transaction_id, listing_id, item_id, facility_code).
     */
    public function hasConsumedFor(
        string $transactionId,
        string $listingId,
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
    ): bool {
        $sql = 'SELECT 1 FROM inventory_stock_movements '
            . 'WHERE transaction_id = :tx AND listing_id = :listing '
            . 'AND item_id = :item AND facility_code = :facility '
            . 'LIMIT 1';

        $result = $this->connection->fetchOne($sql, [
            'tx' => $transactionId,
            'listing' => $listingId,
            'item' => $itemId->value,
            'facility' => $facilityCode->value,
        ]);

        return $result !== false;
    }

    private function insert(
        string $kind,
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        ?StockBatchId $stockBatchId,
        StockMovementReason $reason,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        ?string $transactionId,
        DateTimeImmutable $recordedAt,
        ?string $operatorNote,
        ?string $listingId = null,
    ): void {
        $this->connection->insert('inventory_stock_movements', [
            'id' => $this->identityGenerator->nextStockMovementId()->value,
            'item_id' => $itemId->value,
            'facility_code' => $facilityCode->value,
            'stock_batch_id' => $stockBatchId?->value,
            'kind' => $kind,
            'reason' => $reason->value,
            'quantity' => $quantity->units,
            'cost_per_unit_cents' => $costPerUnit->cents,
            'operator_note' => $operatorNote,
            'transaction_id' => $transactionId,
            'listing_id' => $listingId,
            'recorded_at' => $recordedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }
}
