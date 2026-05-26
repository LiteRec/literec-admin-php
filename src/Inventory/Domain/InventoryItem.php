<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Event\InventoryItemArchived;
use App\Inventory\Domain\Event\InventoryItemPosColorUpdated;
use App\Inventory\Domain\Event\InventoryItemPrimaryVendorUpdated;
use App\Inventory\Domain\Event\InventoryItemRegistered;
use App\Inventory\Domain\Event\InventoryItemRentableChanged;
use App\Inventory\Domain\Event\InventoryItemReorderThresholdUpdated;
use App\Inventory\Domain\Event\InventoryItemTrackingChanged;
use App\Inventory\Domain\Event\StockMovementRecorded;
use App\Inventory\Domain\Event\StockReceived;
use App\Inventory\Domain\Event\StockReturned;
use App\Inventory\Domain\Exception\InsufficientStock;
use App\Inventory\Domain\Exception\InvalidStockReturn;
use App\Inventory\Domain\Exception\InventoryItemIsArchived;
use App\Inventory\Domain\ValueObject\Comment;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use App\Inventory\Domain\ValueObject\VendorId;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Psr\Clock\ClockInterface;

/**
 * Inventory item aggregate skeleton.
 *
 * Pure domain class: no Symfony or Doctrine imports. State changes flow
 * through intention-revealing methods that buffer domain events; the
 * application service releases them after the persistence transaction
 * commits.
 *
 * Cross-context reference to the Catalog Listing is by {@see ListingId}
 * value only — no Doctrine association across the Inventory/Catalog
 * boundary. Existence and kind validation for the referenced listing is
 * the responsibility of the registration application service (LRA-78)
 * via a Catalog read-model port.
 *
 * Stock batches (LRA-74) and per-facility storage (LRA-75) are layered
 * onto this aggregate by subsequent tickets in this sprint.
 */
final class InventoryItem
{
    use AggregateRoot;

    private InventoryItemId $id;

    private ListingId $listingId;

    private ?VendorId $primaryVendorId;

    private PosColor $posColor;

    private bool $trackInventory;

    private bool $rentable;

    private ReorderThreshold $reorderThreshold;

    private bool $archived;

    private DateTimeImmutable $registeredAt;

    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, StockBatch> */
    private Collection $batches;

    private function __construct()
    {
        // Intentionally empty. Construction goes through the
        // self::register(...) named factory so every InventoryItem is
        // born valid and emits an InventoryItemRegistered domain event;
        // direct instantiation is impossible.
        $this->batches = new ArrayCollection();
    }

    public static function register(
        InventoryItemId $id,
        ListingId $listingId,
        ?VendorId $primaryVendorId,
        PosColor $posColor,
        bool $trackInventory,
        bool $rentable,
        ReorderThreshold $reorderThreshold,
        ClockInterface $clock,
    ): self {
        $item = new self();
        $item->id = $id;
        $item->listingId = $listingId;
        $item->primaryVendorId = $primaryVendorId;
        $item->posColor = $posColor;
        $item->trackInventory = $trackInventory;
        $item->rentable = $rentable;
        $item->reorderThreshold = $reorderThreshold;
        $item->archived = false;
        $item->registeredAt = $clock->now();
        $item->updatedAt = $item->registeredAt;

        $item->recordThat(new InventoryItemRegistered(
            $id,
            $listingId,
            $primaryVendorId,
            $posColor,
            $trackInventory,
            $rentable,
            $reorderThreshold,
            $item->registeredAt,
        ));

        return $item;
    }

    public function id(): InventoryItemId
    {
        return $this->id;
    }

    public function listingId(): ListingId
    {
        return $this->listingId;
    }

    public function primaryVendorId(): ?VendorId
    {
        return $this->primaryVendorId;
    }

    public function posColor(): PosColor
    {
        return $this->posColor;
    }

    public function tracksInventory(): bool
    {
        return $this->trackInventory;
    }

    public function isRentable(): bool
    {
        return $this->rentable;
    }

    public function reorderThreshold(): ReorderThreshold
    {
        return $this->reorderThreshold;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function enableTracking(ClockInterface $clock): void
    {
        $this->changeTracking(true, $clock);
    }

    public function disableTracking(ClockInterface $clock): void
    {
        $this->changeTracking(false, $clock);
    }

    public function markRentable(ClockInterface $clock): void
    {
        $this->changeRentable(true, $clock);
    }

    public function markNonRentable(ClockInterface $clock): void
    {
        $this->changeRentable(false, $clock);
    }

    public function updatePosColor(PosColor $posColor, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if ($this->posColor->equals($posColor)) {
            return;
        }

        $this->posColor = $posColor;
        $this->updatedAt = $clock->now();
        $this->recordThat(new InventoryItemPosColorUpdated($this->id, $posColor, $this->updatedAt));
    }

    public function updatePrimaryVendor(?VendorId $primaryVendorId, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if (self::vendorIdsEqual($this->primaryVendorId, $primaryVendorId)) {
            return;
        }

        $this->primaryVendorId = $primaryVendorId;
        $this->updatedAt = $clock->now();
        $this->recordThat(new InventoryItemPrimaryVendorUpdated($this->id, $primaryVendorId, $this->updatedAt));
    }

    public function updateReorderThreshold(ReorderThreshold $reorderThreshold, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if ($this->reorderThreshold->equals($reorderThreshold)) {
            return;
        }

        $this->reorderThreshold = $reorderThreshold;
        $this->updatedAt = $clock->now();
        $this->recordThat(new InventoryItemReorderThresholdUpdated($this->id, $reorderThreshold, $this->updatedAt));
    }

    /**
     * @return list<StockBatch>
     */
    public function batches(): array
    {
        return $this->sortedBatches();
    }

    public function totalOnHand(): Quantity
    {
        $total = Quantity::zero();
        foreach ($this->batches as $batch) {
            $total = $total->add($batch->remainingQuantity());
        }
        return $total;
    }

    public function receiveBatch(
        Quantity $quantity,
        CostPerUnit $cost,
        ?PurchaseOrderLineId $sourceLineId,
        ?Comment $comments,
        StockBatchId $newBatchId,
        ClockInterface $clock,
    ): StockBatchId {
        $this->guardNotArchived();

        $batch = StockBatch::receive(
            $newBatchId,
            $this->id,
            $quantity,
            $cost,
            $sourceLineId,
            $comments,
            $clock,
        );
        $this->batches->add($batch);

        $this->updatedAt = $clock->now();
        $this->recordThat(new StockReceived(
            $this->id,
            $newBatchId,
            $quantity,
            $cost,
            $sourceLineId,
            $comments,
            $this->updatedAt,
        ));

        return $newBatchId;
    }

    public function consume(Quantity $quantity, StockMovementReason $reason, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if ($quantity->isZero()) {
            return;
        }

        $sorted = $this->sortedBatches();
        $available = $this->totalOnHand();

        if ($quantity->units > $available->units) {
            throw InsufficientStock::for($this->id, $quantity, $available);
        }

        $occurredAt = $clock->now();
        $outstanding = $quantity;

        foreach ($sorted as $batch) {
            if ($outstanding->isZero()) {
                break;
            }
            if ($batch->isEmpty()) {
                continue;
            }

            $consumed = $batch->consume($outstanding);
            $outstanding = $outstanding->subtract($consumed);

            $this->recordThat(new StockMovementRecorded(
                $this->id,
                $batch->id(),
                $consumed,
                $batch->costPerUnit(),
                $reason,
                $occurredAt,
            ));
        }

        $this->updatedAt = $occurredAt;
    }

    public function returnUnits(Quantity $quantity, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if ($quantity->isZero()) {
            return;
        }

        $totalConsumed = Quantity::zero();
        foreach ($this->batches as $batch) {
            $totalConsumed = $totalConsumed->add($batch->consumedQuantity());
        }

        if ($quantity->units > $totalConsumed->units) {
            throw InvalidStockReturn::for($this->id, $quantity, $totalConsumed);
        }

        $reverseConsumed = $this->sortedBatches();
        // LIFO on the most-recently-consumed: walk batches in reverse
        // received-order, restoring to any batch that has consumed units.
        $reverseConsumed = array_reverse($reverseConsumed);

        $occurredAt = $clock->now();
        $outstanding = $quantity;

        foreach ($reverseConsumed as $batch) {
            if ($outstanding->isZero()) {
                break;
            }
            if ($batch->consumedQuantity()->isZero()) {
                continue;
            }

            $restored = $batch->restore($outstanding);
            if ($restored->isZero()) {
                continue;
            }
            $outstanding = $outstanding->subtract($restored);

            $this->recordThat(new StockReturned(
                $this->id,
                $batch->id(),
                $restored,
                $batch->costPerUnit(),
                $occurredAt,
            ));
        }

        $this->updatedAt = $occurredAt;
    }

    public function archive(ClockInterface $clock): void
    {
        if ($this->archived) {
            return;
        }

        $this->archived = true;
        $this->updatedAt = $clock->now();
        $this->recordThat(new InventoryItemArchived($this->id, $this->updatedAt));
    }

    private function changeTracking(bool $next, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if ($this->trackInventory === $next) {
            return;
        }

        $this->trackInventory = $next;
        $this->updatedAt = $clock->now();
        $this->recordThat(new InventoryItemTrackingChanged($this->id, $next, $this->updatedAt));
    }

    private function changeRentable(bool $next, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if ($this->rentable === $next) {
            return;
        }

        $this->rentable = $next;
        $this->updatedAt = $clock->now();
        $this->recordThat(new InventoryItemRentableChanged($this->id, $next, $this->updatedAt));
    }

    private function guardNotArchived(): void
    {
        if ($this->archived) {
            throw InventoryItemIsArchived::for($this->id);
        }
    }

    /**
     * @return list<StockBatch>
     */
    private function sortedBatches(): array
    {
        $batches = iterator_to_array($this->batches, false);
        usort(
            $batches,
            static function (StockBatch $a, StockBatch $b): int {
                $cmp = $a->receivedAt() <=> $b->receivedAt();
                return $cmp !== 0 ? $cmp : strcmp($a->id()->value, $b->id()->value);
            },
        );
        return $batches;
    }

    private static function vendorIdsEqual(?VendorId $a, ?VendorId $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        if ($a === null || $b === null) {
            return false;
        }

        return $a->equals($b);
    }
}
