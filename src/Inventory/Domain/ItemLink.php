<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Event\ItemLinked;
use App\Inventory\Domain\Event\ItemLinkUpdated;
use App\Inventory\Domain\Event\ItemUnlinked;
use App\Inventory\Domain\Exception\LinkToSelfForbidden;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use App\Inventory\Domain\ValueObject\LinkViolation;
use App\Inventory\Domain\ValueObject\Quantity;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Parent-child availability rule between two InventoryItems.
 *
 * The "master" item is the sellable parent; the "linked" item is the
 * dependency whose availability constrains the sale. Five fields govern
 * the rule: reservedQuantity, unlimited, minRequired, maxPerPurchase,
 * includeUntil.
 *
 * The {@see wouldViolateAt()} helper is the pure pre-check the LRA-83
 * ACL handler calls before decrementing stock. ItemLink itself never
 * touches a repository — it is a value-pure rule object so the
 * enforcement code stays trivially testable.
 */
final class ItemLink
{
    use AggregateRoot;

    private ItemLinkId $id;
    private InventoryItemId $masterItemId;
    private InventoryItemId $linkedItemId;
    private Quantity $reservedQuantity;
    private bool $unlimited;
    private Quantity $minRequired;
    private Quantity $maxPerPurchase;
    private ?DateTimeImmutable $includeUntil;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
        // Intentionally empty: link creation goes through self::link().
    }

    public static function link(
        ItemLinkId $id,
        InventoryItemId $masterItemId,
        InventoryItemId $linkedItemId,
        Quantity $reservedQuantity,
        bool $unlimited,
        Quantity $minRequired,
        Quantity $maxPerPurchase,
        ?DateTimeImmutable $includeUntil,
        ClockInterface $clock,
    ): self {
        if ($masterItemId->equals($linkedItemId)) {
            throw LinkToSelfForbidden::for($masterItemId);
        }

        $link = new self();
        $link->id = $id;
        $link->masterItemId = $masterItemId;
        $link->linkedItemId = $linkedItemId;
        $link->reservedQuantity = $reservedQuantity;
        $link->unlimited = $unlimited;
        $link->minRequired = $minRequired;
        $link->maxPerPurchase = $maxPerPurchase;
        $link->includeUntil = $includeUntil;
        $link->createdAt = $clock->now();
        $link->updatedAt = $link->createdAt;

        $link->recordThat(new ItemLinked(
            $id,
            $masterItemId,
            $linkedItemId,
            $reservedQuantity,
            $unlimited,
            $minRequired,
            $maxPerPurchase,
            $includeUntil,
            $link->createdAt,
        ));

        return $link;
    }

    public function id(): ItemLinkId
    {
        return $this->id;
    }

    public function masterItemId(): InventoryItemId
    {
        return $this->masterItemId;
    }

    public function linkedItemId(): InventoryItemId
    {
        return $this->linkedItemId;
    }

    public function reservedQuantity(): Quantity
    {
        return $this->reservedQuantity;
    }

    public function unlimited(): bool
    {
        return $this->unlimited;
    }

    public function minRequired(): Quantity
    {
        return $this->minRequired;
    }

    public function maxPerPurchase(): Quantity
    {
        return $this->maxPerPurchase;
    }

    public function includeUntil(): ?DateTimeImmutable
    {
        return $this->includeUntil;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(
        Quantity $reservedQuantity,
        bool $unlimited,
        Quantity $minRequired,
        Quantity $maxPerPurchase,
        ?DateTimeImmutable $includeUntil,
        ClockInterface $clock,
    ): void {
        if (
            $this->reservedQuantity->equals($reservedQuantity)
            && $this->unlimited === $unlimited
            && $this->minRequired->equals($minRequired)
            && $this->maxPerPurchase->equals($maxPerPurchase)
            && self::datesEqual($this->includeUntil, $includeUntil)
        ) {
            return;
        }

        $this->reservedQuantity = $reservedQuantity;
        $this->unlimited = $unlimited;
        $this->minRequired = $minRequired;
        $this->maxPerPurchase = $maxPerPurchase;
        $this->includeUntil = $includeUntil;
        $this->updatedAt = $clock->now();

        $this->recordThat(new ItemLinkUpdated(
            $this->id,
            $reservedQuantity,
            $unlimited,
            $minRequired,
            $maxPerPurchase,
            $includeUntil,
            $this->updatedAt,
        ));
    }

    /**
     * Marks the link as unlinked by recording {@see ItemUnlinked}. The
     * repository is responsible for the row-level deletion (the
     * application service calls repository->remove() right after).
     */
    public function unlink(ClockInterface $clock): void
    {
        $this->updatedAt = $clock->now();
        $this->recordThat(new ItemUnlinked($this->id, $this->updatedAt));
    }

    /**
     * Pure pre-check the LRA-83 ACL handler calls before decrementing
     * stock for a master-item sale.
     *
     * Returns null when the link does not block the sale; otherwise
     * returns the named violation. The caller is responsible for
     * fetching the live stock numbers and passing them in; this method
     * never touches a repository.
     *
     * Semantics:
     *  - `includeUntil` in the past → link is inert (returns null).
     *  - `unlimited === true` → skip the stock-floor check (still
     *    enforces min/max).
     *  - `reservedQuantity > 0` AND
     *    (linkedStockOnHand − linkedReservedAlready − linkedQtyInThisPurchase)
     *      < reservedQuantity → ReservedQuantityBreached.
     *  - linkedQtyInThisPurchase < minRequired * masterQtySold →
     *    MinRequiredNotMet.
     *  - linkedQtyInThisPurchase > maxPerPurchase →
     *    MaxPerPurchaseExceeded.
     */
    public function wouldViolateAt(
        DateTimeImmutable $now,
        Quantity $masterQtySold,
        Quantity $linkedStockOnHand,
        Quantity $linkedReservedAlready,
        Quantity $linkedQtyInThisPurchase,
    ): ?LinkViolation {
        if ($this->includeUntil !== null && $now > $this->includeUntil) {
            return null;
        }

        if (! $this->unlimited && $this->reservedQuantity->units > 0) {
            $reserved = $linkedReservedAlready->units + $linkedQtyInThisPurchase->units;
            $available = $linkedStockOnHand->units - $reserved;
            if ($available < $this->reservedQuantity->units) {
                return LinkViolation::RESERVED_QUANTITY_BREACHED;
            }
        }

        $required = $this->minRequired->units * $masterQtySold->units;
        if ($linkedQtyInThisPurchase->units < $required) {
            return LinkViolation::MIN_REQUIRED_NOT_MET;
        }

        if (
            $this->maxPerPurchase->units > 0
            && $linkedQtyInThisPurchase->units > $this->maxPerPurchase->units
        ) {
            return LinkViolation::MAX_PER_PURCHASE_EXCEEDED;
        }

        return null;
    }

    private static function datesEqual(?DateTimeImmutable $a, ?DateTimeImmutable $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }
        return $a->getTimestamp() === $b->getTimestamp();
    }
}
