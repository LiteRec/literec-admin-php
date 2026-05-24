<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine;

use App\Households\Domain\Exception\DuplicateMemberCode;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Household;
use App\Households\Domain\HouseholdMember;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * Doctrine adapter for the {@see Households} port. The only class under
 * src/Households/ that imports {@see EntityManagerInterface} (enforced
 * by Deptrac).
 *
 * Translates Postgres unique-constraint violations on
 * (household_id, code) into the domain's {@see DuplicateMemberCode}
 * exception so callers in the Application layer can react in domain
 * terms regardless of which adapter is wired.
 */
final class DoctrineHouseholds implements Households
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Household $household): void
    {
        try {
            $this->em->persist($household);
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            if (
                str_contains($e->getMessage(), 'UNIQ_household_members_household_code')
                || str_contains($e->getMessage(), 'uniq_household_members_household_code')
            ) {
                throw $this->firstDuplicateMemberCode($household);
            }

            throw $e;
        }
    }

    public function findById(HouseholdId $id): Household
    {
        $household = $this->em->find(Household::class, $id);

        if (!$household instanceof Household) {
            throw HouseholdNotFound::byId($id);
        }

        return $household;
    }

    public function findByMemberId(MemberId $id): Household
    {
        $household = $this->em->createQueryBuilder()
            ->select('h', 'm')
            ->from(Household::class, 'h')
            ->join('h.members', 'm')
            ->where('m.id = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$household instanceof Household) {
            throw HouseholdNotFound::byMemberId($id);
        }

        return $household;
    }

    public function findByMemberCode(MemberCode $code): Household
    {
        $household = $this->em->createQueryBuilder()
            ->select('h', 'm')
            ->from(Household::class, 'h')
            ->join('h.members', 'm')
            ->where('m.code = :code')
            ->setParameter('code', $code)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$household instanceof Household) {
            throw HouseholdNotFound::byMemberCode($code);
        }

        return $household;
    }

    /**
     * Resolves the actual conflicting member code on a (household_id, code)
     * unique-constraint violation. The EntityManager is closed by the
     * failed flush, so we go through the raw DBAL connection — which
     * survives — to look up which of the in-memory member codes already
     * exists in the database for the same household.
     */
    private function firstDuplicateMemberCode(Household $household): DuplicateMemberCode
    {
        $candidateCodes = array_map(
            static fn(HouseholdMember $m): string => $m->code()->value,
            $household->members(),
        );

        if ($candidateCodes === []) {
            // Unreachable in practice — a unique-index violation on
            // household_members necessarily came from an in-memory member
            // with a code.
            throw new LogicException(
                'UniqueConstraint on household_members fired without an in-memory member.',
            );
        }

        $collidingCode = $this->em->getConnection()->fetchOne(
            'SELECT code FROM household_members WHERE household_id = :hid AND code IN (:codes) LIMIT 1',
            [
                'hid'   => $household->id()->value,
                'codes' => $candidateCodes,
            ],
            [
                'codes' => ArrayParameterType::STRING,
            ],
        );

        if (!is_string($collidingCode)) {
            // The unique index fired but we cannot identify the colliding
            // code (e.g. concurrent delete between flush and the lookup).
            // Fall back to the first in-memory candidate so the caller
            // still receives a typed domain exception.
            return DuplicateMemberCode::for(MemberCode::of($candidateCodes[0]));
        }

        return DuplicateMemberCode::for(MemberCode::of($collidingCode));
    }
}
