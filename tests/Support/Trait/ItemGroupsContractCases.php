<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Inventory\Domain\Exception\ItemGroupNotFound;
use App\Inventory\Domain\ItemGroup;
use App\Inventory\Domain\ItemGroups;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\FacilityScope;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemGroupName;
use App\Inventory\Domain\ValueObject\PosColor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared behavioural contract for any {@see ItemGroups} adapter. The
 * InMemory and Doctrine drivers both pin to this trait so the
 * implementations cannot drift.
 */
trait ItemGroupsContractCases
{
    private const GROUP_A = '019571bf-5d51-7000-b500-00000000aa01';
    private const GROUP_B = '019571bf-5d51-7000-b500-00000000aa02';
    private const ITEM_X = '019571bf-5d51-7000-b500-00000000ab01';
    private const ITEM_Y = '019571bf-5d51-7000-b500-00000000ab02';

    abstract protected function itemGroups(): ItemGroups;

    abstract protected function clock(): MockClock;

    #[Test]
    #[TestDox('add() then byId() round-trips name, color, facility scope, and members.')]
    public function add_then_by_id_round_trips(): void
    {
        $group = ItemGroup::create(
            ItemGroupId::fromString(self::GROUP_A),
            ItemGroupName::of('Beverages'),
            PosColor::ofHex('#1188FF'),
            FacilityScope::ofFacilities([FacilityCode::fromString('MAIN')]),
            $this->clock(),
        );
        $group->addItem(InventoryItemId::fromString(self::ITEM_X), $this->clock());
        $group->addItem(InventoryItemId::fromString(self::ITEM_Y), $this->clock());
        $group->releaseEvents();
        $this->itemGroups()->add($group);

        $loaded = $this->itemGroups()->byId(ItemGroupId::fromString(self::GROUP_A));
        self::assertSame('Beverages', $loaded->name()->value);
        self::assertSame('#1188FF', $loaded->color()->hex);
        self::assertFalse($loaded->facilityScope()->isAll);
        self::assertSame('MAIN', $loaded->facilityScope()->facilities[0]->value);
        $memberIds = array_map(
            static fn (InventoryItemId $id): string => $id->value,
            $loaded->members(),
        );
        sort($memberIds);
        self::assertSame([self::ITEM_X, self::ITEM_Y], $memberIds);
    }

    #[Test]
    #[TestDox('byName() returns the matching group.')]
    public function by_name_returns_matching_group(): void
    {
        $group = ItemGroup::create(
            ItemGroupId::fromString(self::GROUP_A),
            ItemGroupName::of('Snacks'),
            PosColor::default(),
            FacilityScope::all(),
            $this->clock(),
        );
        $group->releaseEvents();
        $this->itemGroups()->add($group);

        $loaded = $this->itemGroups()->byName(ItemGroupName::of('Snacks'));
        self::assertSame(self::GROUP_A, $loaded->id()->value);
    }

    #[Test]
    #[TestDox('byName() throws ItemGroupNotFound when no group matches.')]
    public function by_name_throws_when_missing(): void
    {
        $this->expectException(ItemGroupNotFound::class);
        $this->itemGroups()->byName(ItemGroupName::of('NotThere'));
    }

    #[Test]
    #[TestDox('forItem() returns matching groups in newest-first added_at order.')]
    public function for_item_returns_matches_in_recency_order(): void
    {
        $this->seedGroup(self::GROUP_A, 'Group A', [self::ITEM_X, self::ITEM_Y]);
        // Advance the clock so GROUP_B's membership added_at lands
        // strictly after GROUP_A's — without this the seed timestamps
        // collide and the contract cannot verify the recency ordering
        // that LRA-97 read paths depend on.
        $this->clock()->sleep(1);
        $this->seedGroup(self::GROUP_B, 'Group B', [self::ITEM_X]);

        $result = $this->itemGroups()->forItem(InventoryItemId::fromString(self::ITEM_X));

        self::assertCount(2, $result, 'both groups containing ITEM_X are returned');
        $ids = array_map(static fn (ItemGroup $g): string => $g->id()->value, $result);
        self::assertSame([self::GROUP_B, self::GROUP_A], $ids, 'newest membership first');
    }

    #[Test]
    #[TestDox('availableAtFacility() includes ALL-scope groups and matching facility-scoped groups.')]
    public function available_at_facility_includes_all_scope_and_matching(): void
    {
        $allScope = ItemGroup::create(
            ItemGroupId::fromString(self::GROUP_A),
            ItemGroupName::of('Group All'),
            PosColor::default(),
            FacilityScope::all(),
            $this->clock(),
        );
        $allScope->releaseEvents();
        $this->itemGroups()->add($allScope);

        $facilityScoped = ItemGroup::create(
            ItemGroupId::fromString(self::GROUP_B),
            ItemGroupName::of('Group MAIN'),
            PosColor::default(),
            FacilityScope::ofFacilities([FacilityCode::fromString('MAIN')]),
            $this->clock(),
        );
        $facilityScoped->releaseEvents();
        $this->itemGroups()->add($facilityScoped);

        $result = $this->itemGroups()->availableAtFacility(FacilityCode::fromString('MAIN'));
        $ids = array_map(static fn (ItemGroup $g): string => $g->id()->value, $result);
        sort($ids);
        self::assertSame([self::GROUP_A, self::GROUP_B], $ids);

        $otherFacility = $this->itemGroups()->availableAtFacility(FacilityCode::fromString('LAKE'));
        $otherIds = array_map(static fn (ItemGroup $g): string => $g->id()->value, $otherFacility);
        self::assertSame([self::GROUP_A], $otherIds, 'only ALL-scope group matches a different facility');
    }

    #[Test]
    #[TestDox('save() persists addItem and removeItem mutations across reloads.')]
    public function save_persists_membership_mutations(): void
    {
        $this->seedGroup(self::GROUP_A, 'Group A', [self::ITEM_X]);

        $loaded = $this->itemGroups()->byId(ItemGroupId::fromString(self::GROUP_A));
        $loaded->addItem(InventoryItemId::fromString(self::ITEM_Y), $this->clock());
        $loaded->removeItem(InventoryItemId::fromString(self::ITEM_X), $this->clock());
        $loaded->releaseEvents();
        $this->itemGroups()->save($loaded);

        $reloaded = $this->itemGroups()->byId(ItemGroupId::fromString(self::GROUP_A));
        $ids = array_map(
            static fn (InventoryItemId $id): string => $id->value,
            $reloaded->members(),
        );
        self::assertSame([self::ITEM_Y], $ids);
    }

    /**
     * @param list<string> $itemIds
     */
    private function seedGroup(string $id, string $name, array $itemIds): ItemGroup
    {
        $group = ItemGroup::create(
            ItemGroupId::fromString($id),
            ItemGroupName::of($name),
            PosColor::default(),
            FacilityScope::all(),
            $this->clock(),
        );
        foreach ($itemIds as $itemId) {
            $group->addItem(InventoryItemId::fromString($itemId), $this->clock());
        }
        $group->releaseEvents();
        $this->itemGroups()->add($group);

        return $group;
    }
}
