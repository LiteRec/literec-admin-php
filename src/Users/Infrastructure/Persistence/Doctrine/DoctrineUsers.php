<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Persistence\Doctrine;

use App\Users\Domain\Exception\UserNotFound;
use App\Users\Domain\Exception\UsernameAlreadyTaken;
use App\Users\Domain\User;
use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\UserId;
use App\Users\Domain\ValueObject\Username;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine adapter for the {@see Users} port. The only class under
 * src/Users/ that imports {@see EntityManagerInterface} (enforced by Deptrac).
 */
final class DoctrineUsers implements Users
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(User $user): void
    {
        try {
            $this->em->persist($user);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw UsernameAlreadyTaken::for($user->username()->value);
        }
    }

    public function save(User $user): void
    {
        $this->em->flush();
    }

    public function byId(UserId $id): User
    {
        $user = $this->em->find(User::class, $id);

        if (!$user instanceof User) {
            throw UserNotFound::byId($id->value);
        }

        return $user;
    }

    public function byUsername(Username $username): User
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        if (!$user instanceof User) {
            throw UserNotFound::byUsername($username->value);
        }

        return $user;
    }

    public function existsWithUsername(Username $username): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('1')
            ->from(User::class, 'u')
            ->where('u.username = :username')
            ->setParameter('username', $username)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }
}
