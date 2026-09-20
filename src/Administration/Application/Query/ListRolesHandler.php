<?php

declare(strict_types=1);

namespace App\Administration\Application\Query;

use App\Administration\Application\Query\Port\RoleReadModel;
use App\Administration\Application\Query\View\RoleListPage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListRolesHandler
{
    public function __construct(
        private readonly RoleReadModel $roles,
    ) {
    }

    public function __invoke(ListRoles $query): RoleListPage
    {
        return new RoleListPage($this->roles->listRoles($query->includeRetired));
    }
}
