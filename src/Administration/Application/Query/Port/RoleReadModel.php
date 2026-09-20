<?php

declare(strict_types=1);

namespace App\Administration\Application\Query\Port;

use App\Administration\Application\Query\View\RoleDetailView;
use App\Administration\Application\Query\View\RoleSummaryView;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\ValueObject\RoleId;

/**
 * Read-side port for the Administration context.
 *
 * Sits in the Application layer because it is not a domain invariant (no
 * aggregate consistency boundary) but a use-case dependency the query
 * handlers inject. The Doctrine adapter queries the same table as the
 * write side via direct DBAL SQL (CQRS-lite); no aggregate ever leaves
 * the Application layer.
 */
interface RoleReadModel
{
    /**
     * @return list<RoleSummaryView>
     */
    public function listRoles(bool $includeRetired): array;

    /**
     * @throws RoleNotFound when no role has this id.
     */
    public function roleDetail(RoleId $id): RoleDetailView;
}
