<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Combo;
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
        // JOIN FETCH so the returned combos arrive with their components
        // hydrated in the same query — the LRA-83 ACL sale-time expansion
        // walks every component immediately after this call and would
        // otherwise trigger N+1 loads on the lazy collection.
        $dql = 'SELECT DISTINCT c FROM ' . Combo::class . ' c '
            . 'JOIN FETCH c.components cc '
            . 'WHERE cc.componentItemId = :itemId';

        $query = $this->em->createQuery($dql);
        $query->setParameter('itemId', $componentItemId);

        /** @var list<Combo> $result */
        $result = $query->getResult();

        return $result;
    }
}
