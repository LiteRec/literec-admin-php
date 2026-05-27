<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Exception\ConcurrentInventoryItemModification;
use App\Inventory\Domain\Exception\DuplicateInventoryItemForListing;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

/**
 * Doctrine adapter for the {@see InventoryItems} port. The only class
 * under src/Inventory/ that imports {@see EntityManagerInterface}
 * besides {@see DoctrineVendors} (enforced by Deptrac).
 */
final class DoctrineInventoryItems implements InventoryItems
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(InventoryItem $item): void
    {
        try {
            $this->em->persist($item);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw DuplicateInventoryItemForListing::for($item->listingId());
        }
    }

    public function save(InventoryItem $item): void
    {
        if (! $this->em->contains($item)) {
            throw InventoryItemNotFound::withId($item->id());
        }

        try {
            $this->em->flush();
        } catch (OptimisticLockException $e) {
            throw ConcurrentInventoryItemModification::forItem($item->id(), $e);
        }
    }

    public function byId(InventoryItemId $id): InventoryItem
    {
        $item = $this->em->find(InventoryItem::class, $id);

        if (! $item instanceof InventoryItem) {
            throw InventoryItemNotFound::withId($id);
        }

        return $item;
    }

    public function byListingId(ListingId $listingId): InventoryItem
    {
        $item = $this->em->getRepository(InventoryItem::class)
            ->findOneBy(['listingId' => $listingId]);

        if (! $item instanceof InventoryItem) {
            throw InventoryItemNotFound::forListing($listingId);
        }

        return $item;
    }

    public function existsForListing(ListingId $listingId): bool
    {
        $result = $this->em->createQueryBuilder()
            ->select('1')
            ->from(InventoryItem::class, 'i')
            ->where('i.listingId = :listingId')
            ->setParameter('listingId', $listingId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }
}
