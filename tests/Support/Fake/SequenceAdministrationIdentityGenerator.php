<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Administration\Domain\IdentityGenerator;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RoleId;
use LogicException;

/**
 * Test double for the Administration IdentityGenerator port. Returns the
 * next entry from its queue; an exhausted queue throws so a test that
 * accidentally requests more ids than it set up fails loudly.
 */
final class SequenceAdministrationIdentityGenerator implements IdentityGenerator
{
    /** @var list<RoleId> */
    private array $roleQueue;

    /** @var list<RankId> */
    private array $rankQueue;

    /**
     * @param list<RoleId> $roleIds
     * @param list<RankId> $rankIds
     */
    public function __construct(array $roleIds = [], array $rankIds = [])
    {
        $this->roleQueue = $roleIds;
        $this->rankQueue = $rankIds;
    }

    public function nextRoleId(): RoleId
    {
        if ($this->roleQueue === []) {
            throw new LogicException('RoleId identity queue exhausted.');
        }

        return array_shift($this->roleQueue);
    }

    public function nextRankId(): RankId
    {
        if ($this->rankQueue === []) {
            throw new LogicException('RankId identity queue exhausted.');
        }

        return array_shift($this->rankQueue);
    }
}
