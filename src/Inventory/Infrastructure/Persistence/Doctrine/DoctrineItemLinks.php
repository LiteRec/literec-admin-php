<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Domain\Exception\DuplicateItemLink;
use App\Inventory\Domain\Exception\ItemLinkNotFound;
use App\Inventory\Domain\ItemLink;
use App\Inventory\Domain\ItemLinks;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine adapter for the {@see ItemLinks} port.
 *
 * Translates the DBAL {@see UniqueConstraintViolationException} raised
 * by the (master_item_id, linked_item_id) unique constraint into the
 * domain {@see DuplicateItemLink} exception so concurrent inserts
 * cannot race past the in-process existsForPair() check.
 */
final class DoctrineItemLinks implements ItemLinks
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(ItemLink $link): void
    {
        try {
            $this->em->persist($link);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw DuplicateItemLink::forPair($link->masterItemId(), $link->linkedItemId());
        }
    }

    public function save(ItemLink $link): void
    {
        if (! $this->em->contains($link)) {
            throw ItemLinkNotFound::withId($link->id());
        }

        $this->em->flush();
    }

    public function remove(ItemLink $link): void
    {
        if (! $this->em->contains($link)) {
            return;
        }
        $this->em->remove($link);
        $this->em->flush();
    }

    public function byId(ItemLinkId $id): ItemLink
    {
        $link = $this->em->find(ItemLink::class, $id);

        if (! $link instanceof ItemLink) {
            throw ItemLinkNotFound::withId($id);
        }

        return $link;
    }

    public function byPair(InventoryItemId $masterItemId, InventoryItemId $linkedItemId): ItemLink
    {
        $link = $this->em->getRepository(ItemLink::class)->findOneBy([
            'masterItemId' => $masterItemId,
            'linkedItemId' => $linkedItemId,
        ]);

        if (! $link instanceof ItemLink) {
            throw ItemLinkNotFound::forPair($masterItemId, $linkedItemId);
        }

        return $link;
    }

    public function existsForPair(InventoryItemId $masterItemId, InventoryItemId $linkedItemId): bool
    {
        $result = $this->em->createQueryBuilder()
            ->select('1')
            ->from(ItemLink::class, 'l')
            ->where('l.masterItemId = :master')
            ->andWhere('l.linkedItemId = :linked')
            ->setParameter('master', $masterItemId)
            ->setParameter('linked', $linkedItemId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }

    public function activeForMaster(InventoryItemId $masterItemId, DateTimeImmutable $now): array
    {
        /** @var list<ItemLink> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('l')
            ->from(ItemLink::class, 'l')
            ->where('l.masterItemId = :master')
            ->andWhere('l.includeUntil IS NULL OR l.includeUntil >= :now')
            ->setParameter('master', $masterItemId)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
