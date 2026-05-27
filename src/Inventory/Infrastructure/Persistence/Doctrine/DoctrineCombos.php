<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Combo;
use App\Inventory\Domain\ComboComponentEntity;
use App\Inventory\Domain\Combos;
use App\Inventory\Domain\Exception\ComboNotFound;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine adapter for the {@see Combos} port (LRA-95).
 *
 * Replaces the in-memory fail-loud UnimplementedCombos production
 * stub. Combo components are persisted as child rows via the EAGER
 * one-to-many on Combo::components, so byId / byListingId return a
 * fully-hydrated aggregate ready for the LRA-83 ACL sale-time
 * expansion path.
 */
final class DoctrineCombos implements Combos
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(Combo $combo): void
    {
        $this->em->persist($combo);
        $this->em->flush();
    }

    public function save(Combo $combo): void
    {
        if (! $this->em->contains($combo)) {
            throw ComboNotFound::withId($combo->id());
        }

        $this->em->flush();
    }

    public function byId(ComboId $id): Combo
    {
        $combo = $this->em->find(Combo::class, $id);
        if ($combo === null) {
            throw ComboNotFound::withId($id);
        }

        return $combo;
    }

    public function byListingId(ListingId $listingId): Combo
    {
        $combo = $this->em->getRepository(Combo::class)->findOneBy([
            'listingId' => $listingId,
        ]);
        if ($combo === null) {
            throw ComboNotFound::forListing($listingId);
        }

        return $combo;
    }

    /**
     * @return list<Combo>
     */
    public function forComponent(InventoryItemId $componentItemId): array
    {
        // Query through the child table on the IDX_inventory_combo_components_item
        // index, then return distinct combo aggregates. Going via DQL on
        // ComboComponentEntity keeps the query plan tight (single
        // index hit + join back to the parent) and avoids hydrating
        // unrelated combos.
        // Two-step query: first resolve the combo IDs via the child
        // table (single index hit on inventory_combo_components.item_id),
        // then load the parents. The components association is mapped
        // fetch="EAGER" so the second query hydrates the children
        // alongside the parents in a single batch — no N+1 even
        // without JOIN FETCH in the parent query, which the
        // phpstan-doctrine analyser parses incorrectly when combined
        // with DISTINCT.
        $idDql = 'SELECT DISTINCT IDENTITY(cc.combo) FROM '
            . ComboComponentEntity::class . ' cc '
            . 'WHERE cc.componentItemId = :itemId';

        $idQuery = $this->em->createQuery($idDql);
        $idQuery->setParameter('itemId', $componentItemId);
        /** @var list<string> $comboIds */
        $comboIds = array_column($idQuery->getArrayResult(), 1);

        if ($comboIds === []) {
            return [];
        }

        $combosDql = 'SELECT c FROM ' . Combo::class . ' c WHERE c.id IN (:ids)';
        $combosQuery = $this->em->createQuery($combosDql);
        $combosQuery->setParameter('ids', $comboIds);

        /** @var list<Combo> $result */
        $result = $combosQuery->getResult();

        return $result;
    }
}
