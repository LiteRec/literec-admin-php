<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\Exception\ConcurrentRankModification;
use App\Administration\Domain\Exception\DuplicateRankName;
use App\Administration\Domain\Exception\RankNotFound;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleId;

/**
 * Domain port for persisting and retrieving Rank aggregates.
 *
 * Forbids generic finders (`findBy`, `findOneBy`, `createQueryBuilder`
 * etc.); every accessor is named after a domain question staff/admin
 * users actually ask.
 */
interface Ranks
{
    /**
     * @throws DuplicateRankName when a rank with the same name already
     *         exists (caught via the unique constraint on race conditions).
     */
    public function add(Rank $rank): void;

    /**
     * @throws RankNotFound when the rank does not exist.
     * @throws ConcurrentRankModification when the save races a concurrent
     *         modification of the same rank.
     * @throws DuplicateRankName when a rename collides with another
     *         rank's name (caught via the unique constraint).
     */
    public function save(Rank $rank): void;

    /**
     * @throws RankNotFound when no rank has this id.
     */
    public function byId(RankId $id): Rank;

    /**
     * @throws RankNotFound when no rank has this name.
     */
    public function byName(RankName $name): Rank;

    public function existsWithName(RankName $name): bool;

    /**
     * Active ranks ordered by ascending seniority — i.e. most senior
     * first, since ascending {@see \App\Administration\Domain\ValueObject\SeniorityLevel}
     * means less senior.
     *
     * @return list<Rank>
     */
    public function listActiveBySeniority(int $offset, int $limit): array;

    /**
     * Every rank (active or retired) that currently grants $roleId, so
     * LRA-268 can refuse to delete a role still referenced by a rank.
     *
     * @return list<Rank>
     */
    public function listGrantingRole(RoleId $roleId): array;
}
