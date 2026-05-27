<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Domain\Exception\ItemGroupNotFound;
use App\Inventory\Domain\ItemGroup;
use App\Inventory\Domain\ItemGroupMembership;
use App\Inventory\Domain\ItemGroups;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemGroupName;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine adapter for the {@see ItemGroups} port (LRA-96).
 *
 * Replaces the in-memory fail-loud UnimplementedItemGroups production
 * stub. Membership is persisted as inventory_item_group_members child
 * rows; the (item_id, added_at DESC) index backs the per-item
 * recent-groups read path consumed by LRA-97.
 */
final class DoctrineItemGroups implements ItemGroups
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(ItemGroup $group): void
    {
        $this->em->persist($group);
        $this->em->flush();
    }

    public function save(ItemGroup $group): void
    {
        if (! $this->em->contains($group)) {
            throw ItemGroupNotFound::withId($group->id());
        }

        $this->em->flush();
    }

    public function byId(ItemGroupId $id): ItemGroup
    {
        $group = $this->em->find(ItemGroup::class, $id);
        if ($group === null) {
            throw ItemGroupNotFound::withId($id);
        }

        return $group;
    }

    public function byName(ItemGroupName $name): ItemGroup
    {
        $group = $this->em->getRepository(ItemGroup::class)->findOneBy([
            'name' => $name,
        ]);
        if ($group === null) {
            throw ItemGroupNotFound::withName($name);
        }

        return $group;
    }

    /**
     * @return list<ItemGroup>
     */
    public function forItem(InventoryItemId $itemId): array
    {
        // Two-step query so the membership child table drives the
        // index lookup and the parent fetch lands without depending on
        // a JOIN FETCH (the phpstan-doctrine analyser misparses
        // DISTINCT + JOIN FETCH combinations).
        $idDql = 'SELECT DISTINCT IDENTITY(m.group) FROM '
            . ItemGroupMembership::class . ' m '
            . 'WHERE m.itemId = :itemId '
            . 'ORDER BY m.addedAt DESC';

        $idQuery = $this->em->createQuery($idDql);
        $idQuery->setParameter('itemId', $itemId);
        /** @var list<string> $groupIds */
        $groupIds = $idQuery->getSingleColumnResult();

        if ($groupIds === []) {
            return [];
        }

        $groupsDql = 'SELECT g FROM ' . ItemGroup::class . ' g WHERE g.id IN (:ids)';
        $groupsQuery = $this->em->createQuery($groupsDql);
        $groupsQuery->setParameter('ids', $groupIds);

        // Re-order the parent fetch back into the addedAt DESC order
        // the id query returned — the second query's WHERE IN does
        // not preserve input order, but the per-item recent-groups
        // contract requires newest-first.
        /** @var array<string, ItemGroup> $byId */
        $byId = [];
        foreach ($groupsQuery->getResult() as $group) {
            $byId[$group->id()->value] = $group;
        }

        $ordered = [];
        foreach ($groupIds as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    /**
     * @return list<ItemGroup>
     */
    public function availableAtFacility(FacilityCode $facility): array
    {
        // FacilityScope is JSONB; Doctrine cannot push the
        // includes(FacilityCode) check down to SQL, so we hydrate
        // every group and filter in PHP. Acceptable for the
        // expected order-of-magnitude (single-digit hundreds of
        // groups per facility); a follow-up LRA can materialise a
        // facility-scoped read model if scan cost matters.
        /** @var list<ItemGroup> $all */
        $all = $this->em->getRepository(ItemGroup::class)->findAll();

        $matching = [];
        foreach ($all as $group) {
            if ($group->facilityScope()->includes($facility)) {
                $matching[] = $group;
            }
        }

        return $matching;
    }
}
