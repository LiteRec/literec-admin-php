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
use App\Inventory\Domain\Exception\InventoryItemIsArchived;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\VendorId;
use DateTimeImmutable;
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

    private function __construct()
    {
        // Intentionally empty. Construction goes through the
        // self::register(...) named factory so every InventoryItem is
        // born valid and emits an InventoryItemRegistered domain event;
        // direct instantiation is impossible.
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
