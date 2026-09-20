<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine;

use App\Administration\Domain\Exception\ConcurrentRoleModification;
use App\Administration\Domain\Exception\DuplicateRoleName;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\Role;
use App\Administration\Domain\Roles;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

/**
 * Doctrine adapter for the {@see Roles} port. The only class under
 * src/Administration/ allowed to import {@see EntityManagerInterface}
 * (enforced by Deptrac).
 */
final class DoctrineRoles implements Roles
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(Role $role): void
    {
        try {
            $this->em->persist($role);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw DuplicateRoleName::of($role->name()->value);
        }
    }

    public function save(Role $role): void
    {
        if (! $this->em->contains($role)) {
            throw RoleNotFound::withId($role->id());
        }

        try {
            $this->em->flush();
        } catch (OptimisticLockException $e) {
            throw ConcurrentRoleModification::for($role->id()->value, $e);
        } catch (UniqueConstraintViolationException) {
            throw DuplicateRoleName::of($role->name()->value);
        }
    }

    public function byId(RoleId $id): Role
    {
        $role = $this->em->find(Role::class, $id);

        if (! $role instanceof Role) {
            throw RoleNotFound::withId($id);
        }

        return $role;
    }

    /**
     * Case-insensitive lookup via LOWER(r.name), matching {@see RoleName::equals()}'s
     * case-insensitive semantics and the functional unique index the
     * migration creates on LOWER(name).
     */
    public function byName(RoleName $name): Role
    {
        $role = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Role::class, 'r')
            ->where('LOWER(r.name) = :name')
            ->setParameter('name', mb_strtolower($name->value, 'UTF-8'))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (! $role instanceof Role) {
            throw RoleNotFound::withName($name);
        }

        return $role;
    }

    /**
     * Case-insensitive lookup via LOWER(r.name) — see {@see self::byName()}.
     */
    public function existsWithName(RoleName $name): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('1')
            ->from(Role::class, 'r')
            ->where('LOWER(r.name) = :name')
            ->setParameter('name', mb_strtolower($name->value, 'UTF-8'))
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    /**
     * @return list<Role>
     */
    public function allActive(): array
    {
        /** @var list<Role> */
        return $this->em->getRepository(Role::class)->findBy(['retired' => false]);
    }
}
