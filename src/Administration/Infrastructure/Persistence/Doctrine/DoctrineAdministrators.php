<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine;

use App\Administration\Domain\Administrator;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\Exception\AdministratorNotFound;
use App\Administration\Domain\Exception\ConcurrentAdministratorModification;
use App\Administration\Domain\Exception\SignInAccountAlreadyAnAdministrator;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorStanding;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\SignInAccountId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

/**
 * Doctrine adapter for the {@see Administrators} port. The only class
 * under src/Administration/ allowed to import {@see EntityManagerInterface}
 * (enforced by Deptrac).
 *
 * Both child collections (tenures, directly assigned roles) are mapped
 * one-to-many with cascade persist/remove and orphan removal, so
 * persist()/flush() alone is enough to write every change recorded on
 * the aggregate — unlike {@see DoctrineRanks}, which reconciles a
 * cross-aggregate join table with raw DBAL statements, this aggregate's
 * two child tables are its own, ordinary Doctrine associations.
 */
final class DoctrineAdministrators implements Administrators
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(Administrator $administrator): void
    {
        try {
            $this->em->persist($administrator);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw SignInAccountAlreadyAnAdministrator::for($administrator->signInAccountId());
        }
    }

    public function save(Administrator $administrator): void
    {
        if (!$this->em->contains($administrator)) {
            throw AdministratorNotFound::withId($administrator->id());
        }

        try {
            $this->em->flush();
        } catch (OptimisticLockException $e) {
            throw ConcurrentAdministratorModification::for($administrator->id()->value, $e);
        } catch (UniqueConstraintViolationException) {
            throw SignInAccountAlreadyAnAdministrator::for($administrator->signInAccountId());
        }
    }

    public function byId(AdministratorId $id): Administrator
    {
        $administrator = $this->em->find(Administrator::class, $id);

        if (!$administrator instanceof Administrator) {
            throw AdministratorNotFound::withId($id);
        }

        return $administrator;
    }

    public function forSignInAccount(SignInAccountId $id): Administrator
    {
        $administrator = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Administrator::class, 'a')
            ->where('a.signInAccountId = :signInAccountId')
            ->setParameter('signInAccountId', $id->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$administrator instanceof Administrator) {
            throw AdministratorNotFound::forSignInAccount($id);
        }

        return $administrator;
    }

    public function existsForSignInAccount(SignInAccountId $id): bool
    {
        $result = $this->em->createQueryBuilder()
            ->select('1')
            ->from(Administrator::class, 'a')
            ->where('a.signInAccountId = :signInAccountId')
            ->setParameter('signInAccountId', $id->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }

    /**
     * @return list<Administrator>
     */
    public function activeHoldersOfRole(RoleId $roleId): array
    {
        /** @var list<Administrator> */
        return $this->em->createQueryBuilder()
            ->select('a')
            ->from(Administrator::class, 'a')
            ->join('a.roleAssignments', 'ra')
            ->where('ra.roleId = :roleId')
            ->andWhere('a.standing = :standing')
            ->setParameter('roleId', $roleId->value)
            ->setParameter('standing', AdministratorStanding::Active->value)
            ->getQuery()
            ->getResult();
    }
}
