<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Administration\Domain\IdentityGenerator;
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

    /**
     * @param list<RoleId> $roleIds
     */
    public function __construct(array $roleIds = [])
    {
        $this->roleQueue = $roleIds;
    }

    public function nextRoleId(): RoleId
    {
        if ($this->roleQueue === []) {
            throw new LogicException('RoleId identity queue exhausted.');
        }

        return array_shift($this->roleQueue);
    }
}
