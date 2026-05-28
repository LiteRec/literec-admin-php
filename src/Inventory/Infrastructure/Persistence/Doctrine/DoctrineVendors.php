<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Domain\Exception\DuplicateVendorCode;
use App\Inventory\Domain\Exception\VendorNotFound;
use App\Inventory\Domain\Vendor;
use App\Inventory\Domain\Vendors;
use App\Inventory\Domain\ValueObject\VendorCode;
use App\Inventory\Domain\ValueObject\VendorId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine adapter for the {@see Vendors} port. The only class under
 * src/Inventory/ that imports {@see EntityManagerInterface} for vendor
 * persistence (enforced by Deptrac).
 */
final class DoctrineVendors implements Vendors
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(Vendor $vendor): void
    {
        try {
            $this->em->persist($vendor);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw DuplicateVendorCode::for($vendor->code()->value);
        }
    }

    public function save(Vendor $vendor): void
    {
        // save() only persists updates to aggregates that already exist;
        // new aggregates must go through add(). Doctrine treats a
        // never-persisted entity as detached at flush(), silently
        // discarding the call, so we explicitly check the UnitOfWork
        // to keep the port's contract.
        if (! $this->em->contains($vendor)) {
            throw VendorNotFound::byId($vendor->id()->value);
        }

        $this->em->flush();
    }

    public function byId(VendorId $id): Vendor
    {
        $vendor = $this->em->find(Vendor::class, $id);

        if (! $vendor instanceof Vendor) {
            throw VendorNotFound::byId($id->value);
        }

        return $vendor;
    }

    public function byCode(VendorCode $code): Vendor
    {
        $vendor = $this->em->getRepository(Vendor::class)
            ->findOneBy(['code' => $code]);

        if (! $vendor instanceof Vendor) {
            throw VendorNotFound::byCode($code->value);
        }

        return $vendor;
    }

    public function existsWithCode(VendorCode $code): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('1')
            ->from(Vendor::class, 'v')
            ->where('v.code = :code')
            ->setParameter('code', $code)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult() !== null;
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

        /** @var list<Vendor> */
        return $this->em->createQueryBuilder()
            ->select('v')
            ->from(Vendor::class, 'v')
            ->where("LOWER(v.name) LIKE :needle ESCAPE '!'")
            ->setParameter('needle', '%' . $escaped . '%')
            ->orderBy('v.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function listActive(int $offset, int $limit): array
    {
        /** @var list<Vendor> */
        return $this->em->createQueryBuilder()
            ->select('v')
            ->from(Vendor::class, 'v')
            ->where('v.archived = :archived')
            ->setParameter('archived', false)
            ->orderBy('v.code', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
