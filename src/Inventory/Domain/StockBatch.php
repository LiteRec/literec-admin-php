<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\InvalidStockBatchQuantity;
use App\Inventory\Domain\Exception\StockBatchExhausted;
use App\Inventory\Domain\ValueObject\Comment;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\StockBatchId;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Child entity owned by the {@see InventoryItem} aggregate.
 *
 * Although the constructor and mutators are technically `public` (PHP has
 * no package-private modifier), they are considered internal to the
 * aggregate: callers in Application or Infrastructure layers must drive
 * batch lifecycle through {@see InventoryItem} methods. Direct
 * instantiation outside the aggregate is a programming error.
 *
 * Pricing is tracked as a single {@see CostPerUnit} stamped at receipt;
 * FIFO consumption preserves this cost basis so downstream COGS
 * projections roll up cost-of-sales without re-querying purchase orders.
 */
final class StockBatch
{
    private StockBatchId $id;
    private InventoryItemId $itemId;
    private FacilityCode $facilityCode;
    private DateTimeImmutable $receivedAt;
    private Quantity $originalQuantity;
    private Quantity $remainingQuantity;
    private CostPerUnit $costPerUnit;
    private ?PurchaseOrderLineId $sourceLineId;
    private ?Comment $comments;

    private function __construct()
    {
        // Internal-to-aggregate. Use self::receive() via InventoryItem.
    }

    /**
     * @internal Called only by {@see InventoryItem::receiveBatch()}.
     */
    public static function receive(
        StockBatchId $id,
        InventoryItemId $itemId,
        FacilityCode $facilityCode,
        Quantity $quantity,
        CostPerUnit $costPerUnit,
        ?PurchaseOrderLineId $sourceLineId,
        ?Comment $comments,
        ClockInterface $clock,
    ): self {
        if ($quantity->isZero()) {
            throw InvalidStockBatchQuantity::mustBePositive();
        }

        $batch = new self();
        $batch->id = $id;
        $batch->itemId = $itemId;
        $batch->facilityCode = $facilityCode;
        $batch->receivedAt = $clock->now();
        $batch->originalQuantity = $quantity;
        $batch->remainingQuantity = $quantity;
        $batch->costPerUnit = $costPerUnit;
        $batch->sourceLineId = $sourceLineId;
        $batch->comments = $comments;

        return $batch;
    }

    public function id(): StockBatchId
    {
        return $this->id;
    }

    public function itemId(): InventoryItemId
    {
        return $this->itemId;
    }

    public function facilityCode(): FacilityCode
    {
        return $this->facilityCode;
    }

    public function receivedAt(): DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function originalQuantity(): Quantity
    {
        return $this->originalQuantity;
    }

    public function remainingQuantity(): Quantity
    {
        return $this->remainingQuantity;
    }

    public function costPerUnit(): CostPerUnit
    {
        return $this->costPerUnit;
    }

    public function sourceLineId(): ?PurchaseOrderLineId
    {
        return $this->sourceLineId;
    }

    public function comments(): ?Comment
    {
        return $this->comments;
    }

    public function isEmpty(): bool
    {
        return $this->remainingQuantity->isZero();
    }

    /**
     * Already-consumed units = originalQuantity - remainingQuantity.
     * Used by {@see InventoryItem::returnUnits()} to size LIFO restoration.
     */
    public function consumedQuantity(): Quantity
    {
        return $this->originalQuantity->subtract($this->remainingQuantity);
    }

    /**
     * @internal Called only by {@see InventoryItem::consume()}.
     *
     * Consumes up to {@param $requested} units from this batch and returns
     * the {@see Quantity} actually consumed. The caller decrements the
     * outstanding request by the return value and walks to the next batch
     * when this batch empties.
     */
    public function consume(Quantity $requested): Quantity
    {
        if ($this->isEmpty()) {
            throw StockBatchExhausted::for($this->id);
        }

        $consumed = $this->remainingQuantity->greaterThanOrEqual($requested)
            ? $requested
            : $this->remainingQuantity;

        $this->remainingQuantity = $this->remainingQuantity->subtract($consumed);

        return $consumed;
    }

    /**
     * @internal Called only by {@see InventoryItem::returnUnits()}.
     *
     * Restores up to {@param $requested} units back to this batch, capped
     * by how many were previously consumed from it. Returns the amount
     * actually restored.
     */
    public function restore(Quantity $requested): Quantity
    {
        $restorable = $this->consumedQuantity();

        if ($restorable->isZero()) {
            return Quantity::zero();
        }

        $restored = $restorable->greaterThanOrEqual($requested)
            ? $requested
            : $restorable;

        $this->remainingQuantity = $this->remainingQuantity->add($restored);

        return $restored;
    }
}
