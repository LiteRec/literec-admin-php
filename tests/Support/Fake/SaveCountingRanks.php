<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Administration\Domain\Rank;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleId;

/**
 * Decorates a {@see Ranks} adapter, counting add()/save() calls so a
 * handler test can prove persistence was actually requested. Needed
 * because {@see \App\Administration\Infrastructure\Persistence\InMemory\InMemoryRanks}
 * stores the same instance a handler mutates in place: byId() then
 * mutate() already changes what the map holds, so an assertion against
 * the stored aggregate stays green even if the handler under test never
 * calls save() at all. Wrapping the InMemory adapter with this decorator
 * makes that omission fail loudly.
 */
final class SaveCountingRanks implements Ranks
{
    public int $addCalls = 0;
    public int $saveCalls = 0;

    public function __construct(private readonly Ranks $inner)
    {
    }

    public function add(Rank $rank): void
    {
        ++$this->addCalls;
        $this->inner->add($rank);
    }

    public function save(Rank $rank): void
    {
        ++$this->saveCalls;
        $this->inner->save($rank);
    }

    public function byId(RankId $id): Rank
    {
        return $this->inner->byId($id);
    }

    public function byName(RankName $name): Rank
    {
        return $this->inner->byName($name);
    }

    public function existsWithName(RankName $name): bool
    {
        return $this->inner->existsWithName($name);
    }

    public function listActiveBySeniority(int $offset, int $limit): array
    {
        return $this->inner->listActiveBySeniority($offset, $limit);
    }

    public function listGrantingRole(RoleId $roleId): array
    {
        return $this->inner->listGrantingRole($roleId);
    }
}
