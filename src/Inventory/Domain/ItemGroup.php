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
    /** @var array<string, true> InventoryItemId string → true */
    private array $members;
    private bool $archived;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
        // Intentionally empty: aggregate creation goes through self::create().
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
        $group->members = [];
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
        return isset($this->members[$itemId->value]);
    }

    /**
     * @return list<InventoryItemId>
     */
    public function members(): array
    {
        $result = [];
        foreach (array_keys($this->members) as $value) {
            $result[] = InventoryItemId::fromString($value);
        }
        return $result;
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

        if (isset($this->members[$itemId->value])) {
            return;
        }

        $this->members[$itemId->value] = true;
        $this->updatedAt = $clock->now();
        $this->recordThat(new ItemAddedToGroup($this->id, $itemId, $this->updatedAt));
    }

    public function removeItem(InventoryItemId $itemId, ClockInterface $clock): void
    {
        if (! isset($this->members[$itemId->value])) {
            return;
        }

        unset($this->members[$itemId->value]);
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
}
