<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Administration\Domain\IdentityGenerator;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
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

    /** @var list<AdministratorId> */
    private array $administratorQueue;

    /** @var list<AdministratorTenureId> */
    private array $tenureQueue;

    /**
     * @param list<RoleId> $roleIds
     * @param list<RankId> $rankIds
     * @param list<AdministratorId> $administratorIds
     * @param list<AdministratorTenureId> $tenureIds
     */
    public function __construct(
        array $roleIds = [],
        array $rankIds = [],
        array $administratorIds = [],
        array $tenureIds = [],
    ) {
        $this->roleQueue = $roleIds;
        $this->rankQueue = $rankIds;
        $this->administratorQueue = $administratorIds;
        $this->tenureQueue = $tenureIds;
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

    public function nextAdministratorId(): AdministratorId
    {
        if ($this->administratorQueue === []) {
            throw new LogicException('AdministratorId identity queue exhausted.');
        }

        return array_shift($this->administratorQueue);
    }

    public function nextTenureId(): AdministratorTenureId
    {
        if ($this->tenureQueue === []) {
            throw new LogicException('AdministratorTenureId identity queue exhausted.');
        }

        return array_shift($this->tenureQueue);
    }
}
