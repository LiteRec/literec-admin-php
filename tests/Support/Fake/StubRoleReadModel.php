<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Administration\Application\Query\Port\RoleReadModel;
use App\Administration\Application\Query\View\RoleDetailView;
use App\Administration\Application\Query\View\RoleSummaryView;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\ValueObject\RoleId;
use LogicException;

/**
 * Minimal stub feeding canned inbound data to the ListRoles/GetRoleDetail
 * query handlers — both are pure delegation to {@see RoleReadModel}, so a
 * hand-written stub is clearer than a mocking framework.
 */
final class StubRoleReadModel implements RoleReadModel
{
    public ?bool $includeRetiredReceived = null;
    public ?RoleId $roleIdReceived = null;

    /**
     * @param list<RoleSummaryView> $listRolesResult
     */
    public function __construct(
        private readonly array $listRolesResult = [],
        private readonly ?RoleDetailView $roleDetailResult = null,
        private readonly bool $throwsNotFound = false,
    ) {
    }

    public function listRoles(bool $includeRetired): array
    {
        $this->includeRetiredReceived = $includeRetired;

        return $this->listRolesResult;
    }

    public function roleDetail(RoleId $id): RoleDetailView
    {
        $this->roleIdReceived = $id;

        if ($this->throwsNotFound) {
            throw RoleNotFound::withId($id);
        }

        return $this->roleDetailResult ?? throw new LogicException('No roleDetailResult configured.');
    }
}
