<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine;

use App\Catalog\Domain\Exception\DuplicateListingCode;
use App\Catalog\Domain\Exception\ListingNotFound;
use App\Catalog\Domain\Listing;
use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\Listings;
use App\Catalog\Domain\ValueObject\ListingCode;
use App\Catalog\Domain\ValueObject\ListingId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine adapter for the {@see Listings} port. The only class under
 * src/Catalog/ that imports {@see EntityManagerInterface} (enforced by
 * Deptrac).
 */
final class DoctrineListings implements Listings
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(Listing $listing): void
    {
        try {
            $this->em->persist($listing);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw DuplicateListingCode::for($listing->code()->value);
        }
    }

    public function save(Listing $listing): void
    {
        // save() only persists updates to aggregates that already exist;
        // new aggregates must go through add(). Doctrine treats a
        // never-persisted entity as detached at flush(), silently
        // discarding the call, so we explicitly check the UnitOfWork
        // to keep the port's contract.
        if (! $this->em->contains($listing)) {
            throw ListingNotFound::byId($listing->id()->value);
        }

        $this->em->flush();
    }

    public function byId(ListingId $id): Listing
    {
        $listing = $this->em->find(Listing::class, $id);

        if (! $listing instanceof Listing) {
            throw ListingNotFound::byId($id->value);
        }

        return $listing;
    }

    public function byCode(ListingCode $code): Listing
    {
        $listing = $this->em->getRepository(Listing::class)
            ->findOneBy(['code' => $code]);

        if (! $listing instanceof Listing) {
            throw ListingNotFound::byCode($code->value);
        }

        return $listing;
    }

    public function existsWithCode(ListingCode $code): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('1')
            ->from(Listing::class, 'l')
            ->where('l.code = :code')
            ->setParameter('code', $code)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    public function findByKind(ListingKind $kind, int $offset, int $limit): array
    {
        /** @var list<Listing> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('l')
            ->from(Listing::class, 'l')
            ->where('l.kind = :kind')
            ->setParameter('kind', $kind)
            ->orderBy('l.code', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function searchByName(string $query, int $offset, int $limit): array
    {
        $needle = trim($query);

        if ($needle === '') {
            return [];
        }

        // Escape SQL LIKE special characters in the user-supplied needle
        // so "100%" matches the literal substring rather than acting as a
        // wildcard. We declare "!" as the LIKE ESCAPE character (any
        // single character outside the LIKE alphabet works) and double
        // the escape char itself so a needle containing "!" still
        // matches literally.
        $lowered = mb_strtolower($needle, 'UTF-8');
        $escaped = strtr($lowered, ['!' => '!!', '%' => '!%', '_' => '!_']);

        /** @var list<Listing> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('l')
            ->from(Listing::class, 'l')
            ->where("LOWER(l.name) LIKE :needle ESCAPE '!'")
            ->setParameter('needle', '%' . $escaped . '%')
            ->orderBy('l.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
