<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine;

use App\Administration\Domain\Administrator;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\Exception\AdministratorAlreadyExists;
use App\Administration\Domain\Exception\AdministratorNotFound;
use App\Administration\Domain\Exception\ConcurrentAdministratorModification;
use App\Administration\Domain\Exception\SignInAccountAlreadyAnAdministrator;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorStanding;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\SignInAccountId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\EntityIdentityCollisionException;
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
 *
 * add()/save() can each violate one of three distinct unique
 * constraints — the administration_administrators primary key, its
 * sign_in_account_id unique index, and the composite primary key on
 * administration_administrator_roles — and only the second one means
 * "this sign-in account is already an administrator". DBAL does not
 * expose the violated constraint as structured data on
 * {@see UniqueConstraintViolationException}, so {@see self::exceptionFor()}
 * reads it off the driver message, matching the constraint names
 * Postgres actually reports (confirmed against a live violation): the
 * two explicitly named indexes fold to lower case, and the two
 * unnamed primary keys get Postgres's default `<table>_pkey` name.
 *
 * A duplicate id can also surface before any SQL runs, as
 * {@see EntityIdentityCollisionException} from Doctrine's own identity
 * map, when the colliding id is already known to this EntityManager
 * (confirmed against a live add() of two distinct instances sharing an
 * id within the same request) — add() translates that the same way as
 * the primary-key violation, since both mean exactly the same thing at
 * the domain level.
 */
final class DoctrineAdministrators implements Administrators
{
    private const string SIGN_IN_ACCOUNT_CONSTRAINT = 'uniq_administration_administrators_sign_in_account';
    private const string ADMINISTRATOR_PK_CONSTRAINT = 'administration_administrators_pkey';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(Administrator $administrator): void
    {
        try {
            $this->em->persist($administrator);
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw $this->exceptionFor($administrator, $e);
        } catch (EntityIdentityCollisionException) {
            throw AdministratorAlreadyExists::withId($administrator->id());
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
        } catch (UniqueConstraintViolationException $e) {
            throw $this->exceptionFor($administrator, $e);
        }
    }

    /**
     * Translates a unique-constraint violation into the one domain
     * exception it actually names, or rethrows the original DBAL
     * exception unchanged when it violated neither of this aggregate's
     * own two constraints — most notably the composite primary key on
     * administration_administrator_roles, which two concurrent
     * assignRole() calls for the same role can still hit. Reporting
     * that as "sign-in account already an administrator" would send an
     * operator at a completely unrelated problem.
     */
    private function exceptionFor(
        Administrator $administrator,
        UniqueConstraintViolationException $e,
    ): UniqueConstraintViolationException|SignInAccountAlreadyAnAdministrator|AdministratorAlreadyExists {
        if (str_contains($e->getMessage(), self::SIGN_IN_ACCOUNT_CONSTRAINT)) {
            return SignInAccountAlreadyAnAdministrator::for($administrator->signInAccountId());
        }

        if (str_contains($e->getMessage(), self::ADMINISTRATOR_PK_CONSTRAINT)) {
            return AdministratorAlreadyExists::withId($administrator->id());
        }

        return $e;
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
