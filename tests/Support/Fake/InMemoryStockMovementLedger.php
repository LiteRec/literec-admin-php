<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Inventory\Domain\StockMovementLedger;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use DateTimeImmutable;

/**
 * Test double for {@see StockMovementLedger} that records every call in
 * memory. Unit tests assert against {@see rows()} to confirm the right
 * ledger entries fire; functional tests use it as the production
 * binding under when@test so the real DBAL writer never runs.
 *
 * Idempotency mirror: {@see recordConsumed()} throws a
 * {@see DuplicateConsumeKeyException} on a duplicate
 * (transaction_id, item_id, facility_code) tuple — mirrors the
 * partial unique index in Postgres which raises
 * UniqueConstraintViolationException from DBAL. Callers MUST probe
 * {@see hasConsumedFor()} before consuming if they need an idempotent
 * fast-path that avoids the throw.
 */
final class InMemoryStockMovementLedger implements StockMovementLedger
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var array<string, true> */
    private array $consumeKeys = [];

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
    ): void {
        $key = $transactionId . '|' . $itemId->value . '|' . $facilityCode->value;
        if (isset($this->consumeKeys[$key])) {
            throw new DuplicateConsumeKeyException(sprintf(
                'InMemoryStockMovementLedger: duplicate consume key %s '
                . '— mirrors the partial UNIQUE constraint in Postgres.',
                $key,
            ));
        }
        $this->consumeKeys[$key] = true;
        $this->record(
            kind: 'CONSUMED',
            itemId: $itemId,
            facilityCode: $facilityCode,
            stockBatchId: $stockBatchId,
            reason: $reason,
            quantity: $quantity,
            costPerUnit: $costPerUnit,
            transactionId: $transactionId,
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
        $this->record(
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
        $this->record(
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
        $this->record(
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
        $this->record(
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
        CostPerUnit $costPerUnit,
        DateTimeImmutable $recordedAt,
        ?string $operatorNote = null,
    ): void {
        $this->record(
            kind: 'ADJUSTED',
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

    public function hasConsumedFor(
        string $transactionId,
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
    ): bool {
        $key = $transactionId . '|' . $itemId->value . '|' . $facilityCode->value;
        return isset($this->consumeKeys[$key]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    public function reset(): void
    {
        $this->rows = [];
        $this->consumeKeys = [];
    }

    private function record(
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
    ): void {
        $this->rows[] = [
            'kind' => $kind,
            'item_id' => $itemId->value,
            'facility_code' => $facilityCode->value,
            'stock_batch_id' => $stockBatchId?->value,
            'reason' => $reason->value,
            'quantity' => $quantity->units,
            'cost_per_unit_cents' => $costPerUnit->cents,
            'transaction_id' => $transactionId,
            'recorded_at' => $recordedAt,
            'operator_note' => $operatorNote,
        ];
    }
}
