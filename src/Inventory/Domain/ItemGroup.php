<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Event\ItemAddedToGroup;
use App\Inventory\Domain\Event\ItemGroupArchived;
use App\Inventory\Domain\Event\ItemGroupCreated;
use App\Inventory\Domain\Event\ItemGroupRecolored;
use App\Inventory\Domain\Event\ItemGroupRenamed;
use App\Inventory\Domain\Event\ItemRemovedFromGroup;
use App\Inventory\Domain\Exception\ItemGroupArchived as ItemGroupArchivedException;
use App\Inventory\Domain\ValueObject\FacilityScope;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemGroupName;
use App\Inventory\Domain\ValueObject\PosColor;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Psr\Clock\ClockInterface;

/**
 * Categorical grouping aggregate for InventoryItems.
 *
 * Owns the per-group membership invariants (no duplicate items;
 * archived groups reject new members but keep existing ones for
 * historical reporting). InventoryItem has no back-reference to
 * groups — cross-aggregate consistency is eventual via
 * ItemAddedToGroup / ItemRemovedFromGroup events consumed by the read
 * model (LRA-84).
 */
final class ItemGroup
{
    use AggregateRoot;

    private ItemGroupId $id;
    private ItemGroupName $name;
    private PosColor $color;
    private FacilityScope $facilityScope;
    /** @var Collection<int, ItemGroupMembership> */
    private Collection $members;
    private bool $archived;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
        // Doctrine instantiates the aggregate through reflection without
        // invoking create(); initialise the membership collection here
        // so the empty case (a hydrated group with no rows yet) does
        // not leave an undefined property and so the named constructor
        // can immediately mutate the collection rather than re-create it.
        $this->members = new ArrayCollection();
    }

    public static function create(
        ItemGroupId $id,
        ItemGroupName $name,
        PosColor $color,
        FacilityScope $facilityScope,
        ClockInterface $clock,
    ): self {
        $group = new self();
        $group->id = $id;
        $group->name = $name;
        $group->color = $color;
        $group->facilityScope = $facilityScope;
        $group->archived = false;
        $group->createdAt = $clock->now();
        $group->updatedAt = $group->createdAt;

        $group->recordThat(new ItemGroupCreated($id, $name, $color, $facilityScope, $group->createdAt));

        return $group;
    }

    public function id(): ItemGroupId
    {
        return $this->id;
    }

    public function name(): ItemGroupName
    {
        return $this->name;
    }

    public function color(): PosColor
    {
        return $this->color;
    }

    public function facilityScope(): FacilityScope
    {
        return $this->facilityScope;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function hasMember(InventoryItemId $itemId): bool
    {
        return $this->membershipFor($itemId) !== null;
    }

    /**
     * @return list<InventoryItemId>
     */
    public function members(): array
    {
        return array_values(array_map(
            static fn (ItemGroupMembership $m): InventoryItemId => $m->itemId(),
            $this->members->toArray(),
        ));
    }

    public function rename(ItemGroupName $name, ClockInterface $clock): void
    {
        if ($this->archived) {
            throw ItemGroupArchivedException::for($this->id);
        }

        if ($this->name->equals($name)) {
            return;
        }

        $this->name = $name;
        $this->updatedAt = $clock->now();
        $this->recordThat(new ItemGroupRenamed($this->id, $name, $this->updatedAt));
    }

    public function recolor(PosColor $color, ClockInterface $clock): void
    {
        if ($this->archived) {
            throw ItemGroupArchivedException::for($this->id);
        }

        if ($this->color->equals($color)) {
            return;
        }

        $this->color = $color;
        $this->updatedAt = $clock->now();
        $this->recordThat(new ItemGroupRecolored($this->id, $color, $this->updatedAt));
    }

    public function addItem(InventoryItemId $itemId, ClockInterface $clock): void
    {
        if ($this->archived) {
            throw ItemGroupArchivedException::for($this->id);
        }

        if ($this->membershipFor($itemId) !== null) {
            return;
        }

        $now = $clock->now();
        $this->members->add(new ItemGroupMembership($this, $itemId, $now));
        $this->updatedAt = $now;
        $this->recordThat(new ItemAddedToGroup($this->id, $itemId, $this->updatedAt));
    }

    public function removeItem(InventoryItemId $itemId, ClockInterface $clock): void
    {
        // Removal is allowed even after archive — admins still need to
        // prune stray items from archived groups for historical
        // accuracy. The aggregate test
        // archive_then_remove_item_still_emits_event_and_clears_membership
        // pins this behaviour.
        $membership = $this->membershipFor($itemId);
        if ($membership === null) {
            return;
        }

        $this->members->removeElement($membership);
        $this->updatedAt = $clock->now();
        $this->recordThat(new ItemRemovedFromGroup($this->id, $itemId, $this->updatedAt));
    }

    public function archive(ClockInterface $clock): void
    {
        if ($this->archived) {
            return;
        }

        $this->archived = true;
        $this->updatedAt = $clock->now();
        $this->recordThat(new ItemGroupArchived($this->id, $this->updatedAt));
    }

    /**
     * Returns the added_at timestamp of the membership for the given
     * item, or null when the item is not a member. Lets the InMemory
     * adapter sort forItem() results by recency without exposing the
     * full membership entity.
     */
    public function membershipAddedAt(InventoryItemId $itemId): ?DateTimeImmutable
    {
        return $this->membershipFor($itemId)?->addedAt();
    }

    private function membershipFor(InventoryItemId $itemId): ?ItemGroupMembership
    {
        foreach ($this->members as $membership) {
            if ($membership->itemId()->equals($itemId)) {
                return $membership;
            }
        }
        return null;
    }
}
