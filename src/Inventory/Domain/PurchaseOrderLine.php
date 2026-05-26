<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\PurchaseOrderLineOverReceipt;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use DateTimeImmutable;
use LogicException;

/**
 * Child entity owned exclusively by the {@see PurchaseOrder} aggregate.
 *
 * Although mutators are technically public (PHP has no package-private
 * modifier), they are internal to the aggregate: callers in Application
 * or Infrastructure layers drive line lifecycle through
 * {@see PurchaseOrder} methods.
 */
final class PurchaseOrderLine
{
    private PurchaseOrderLineId $id;
    private InventoryItemId $itemId;
    private Quantity $orderedQuantity;
    private Quantity $receivedQuantity;
    private CostPerUnit $costPerUnit;
    private DateTimeImmutable $createdAt;
    /**
     * Back-reference to the owning {@see PurchaseOrder}. Required by the
     * Doctrine many-to-one mapping; set once via {@see attachToOrder()}
     * and never reassigned.
     */
    private PurchaseOrder $purchaseOrder;

    private function __construct()
    {
        // Internal-to-aggregate. Use self::create() via PurchaseOrder.
    }

    /**
     * @internal Called only by {@see PurchaseOrder::createDraft()}.
     */
    public static function create(
        PurchaseOrderLineId $id,
        InventoryItemId $itemId,
        Quantity $orderedQuantity,
        CostPerUnit $costPerUnit,
        DateTimeImmutable $createdAt,
    ): self {
        $line = new self();
        $line->id = $id;
        $line->itemId = $itemId;
        $line->orderedQuantity = $orderedQuantity;
        $line->receivedQuantity = Quantity::zero();
        $line->costPerUnit = $costPerUnit;
        $line->createdAt = $createdAt;

        return $line;
    }

    public function id(): PurchaseOrderLineId
    {
        return $this->id;
    }

    public function itemId(): InventoryItemId
    {
        return $this->itemId;
    }

    public function orderedQuantity(): Quantity
    {
        return $this->orderedQuantity;
    }

    public function receivedQuantity(): Quantity
    {
        return $this->receivedQuantity;
    }

    public function costPerUnit(): CostPerUnit
    {
        return $this->costPerUnit;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isFullyReceived(): bool
    {
        return $this->receivedQuantity->greaterThanOrEqual($this->orderedQuantity);
    }

    /**
     * @internal Called only by {@see PurchaseOrder::receiveLine()}.
     */
    public function receive(Quantity $quantity): void
    {
        $nextTotal = $this->receivedQuantity->add($quantity);

        // $nextTotal > orderedQuantity iff orderedQuantity is NOT >= nextTotal.
        if (! $this->orderedQuantity->greaterThanOrEqual($nextTotal)) {
            throw PurchaseOrderLineOverReceipt::for(
                $this->id,
                $this->orderedQuantity,
                $this->receivedQuantity,
                $quantity,
            );
        }

        $this->receivedQuantity = $nextTotal;
    }

    /**
     * @internal Called by {@see PurchaseOrder::createDraft()}.
     */
    public function attachToOrder(PurchaseOrder $order): void
    {
        if (isset($this->purchaseOrder)) {
            if ($this->purchaseOrder === $order) {
                return;
            }
            throw new LogicException(
                'PurchaseOrderLine is already attached to a different PurchaseOrder.',
            );
        }
        $this->purchaseOrder = $order;
    }
}
