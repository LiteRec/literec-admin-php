<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\InMemory;

use App\Administration\Domain\Exception\DuplicateRankName;
use App\Administration\Domain\Exception\RankNotFound;
use App\Administration\Domain\Rank;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleId;

/**
 * In-memory adapter for the {@see Ranks} port. Used by domain/application
 * unit tests so they stay #[Small] and never boot Doctrine.
 *
 * Unlike {@see \App\Administration\Infrastructure\Persistence\Doctrine\DoctrineRanks},
 * this adapter has no join table to reconcile: the aggregate it stores is
 * the aggregate it returns, so its in-memory {@see \App\Administration\Domain\ValueObject\AssignedRoles}
 * is always already current.
 */
final class InMemoryRanks implements Ranks
{
    /** @var array<string, Rank> indexed by RankId->value */
    private array $byId = [];

    public function add(Rank $rank): void
    {
        if ($this->existsWithName($rank->name())) {
            throw DuplicateRankName::of($rank->name()->value);
        }

        $this->byId[$rank->id()->value] = $rank;
    }

    public function save(Rank $rank): void
    {
        // Mirror DoctrineRanks semantics: save() only persists changes to
        // an already-added aggregate; calling it on an unknown rank is a
        // programming error, not a "create if missing" upsert.
        if (!isset($this->byId[$rank->id()->value])) {
            throw RankNotFound::withId($rank->id());
        }

        foreach ($this->byId as $existingId => $existing) {
            if ($existingId !== $rank->id()->value && $existing->name()->equals($rank->name())) {
                throw DuplicateRankName::of($rank->name()->value);
            }
        }

        $this->byId[$rank->id()->value] = $rank;
    }

    public function byId(RankId $id): Rank
    {
        return $this->byId[$id->value] ?? throw RankNotFound::withId($id);
    }

    public function byName(RankName $name): Rank
    {
        foreach ($this->byId as $rank) {
            if ($rank->name()->equals($name)) {
                return $rank;
            }
        }

        throw RankNotFound::withName($name);
    }

    public function existsWithName(RankName $name): bool
    {
        foreach ($this->byId as $rank) {
            if ($rank->name()->equals($name)) {
                return true;
            }
        }

        return false;
    }

    public function listActiveBySeniority(int $offset, int $limit): array
    {
        $active = array_values(array_filter(
            $this->byId,
            static fn (Rank $rank): bool => !$rank->isRetired(),
        ));

        usort($active, static fn (Rank $a, Rank $b): int => $a->seniority()->value <=> $b->seniority()->value);

        return array_slice($active, $offset, $limit);
    }

    public function listGrantingRole(RoleId $roleId): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (Rank $rank): bool => $rank->roles()->contains($roleId),
        ));
    }
}
