<?php

declare(strict_types=1);

namespace App\Administration\Application\Query;

use App\Administration\Application\Query\Port\RoleReadModel;
use App\Administration\Application\Query\View\RoleDetailView;
use App\Administration\Domain\ValueObject\RoleId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetRoleDetailHandler
{
    public function __construct(
        private readonly RoleReadModel $roles,
    ) {
    }

    public function __invoke(GetRoleDetail $query): RoleDetailView
    {
        return $this->roles->roleDetail(RoleId::fromString($query->roleId));
    }
}
