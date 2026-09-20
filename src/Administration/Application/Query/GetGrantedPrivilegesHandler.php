<?php

declare(strict_types=1);

namespace App\Administration\Application\Query;

use App\Administration\Application\Query\View\GrantedPrivilegesView;
use App\Administration\Application\Security\CurrentAdministrator;
use App\Administration\Domain\EffectivePrivileges;
use App\Administration\Domain\ValueObject\AdministratorId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The read path the UI uses to decide what to render (LRA-270). Resolves
 * through the same {@see EffectivePrivileges} port
 * {@see \App\Administration\Infrastructure\Security\PrivilegeVoter} does
 * — one source of truth, two decisions: this handler decides what to
 * show, the voter decides what to allow, and the server never trusts a
 * client's own copy of either.
 */
#[AsMessageHandler(bus: 'query.bus')]
final class GetGrantedPrivilegesHandler
{
    public function __construct(
        private readonly CurrentAdministrator $currentAdministrator,
        private readonly EffectivePrivileges $effectivePrivileges,
    ) {
    }

    public function __invoke(GetGrantedPrivileges $query): GrantedPrivilegesView
    {
        $standing = $this->currentAdministrator->standing();

        if ($standing === null || !$standing->isActive()) {
            return new GrantedPrivilegesView([]);
        }

        $grants = $this->effectivePrivileges->forAdministrator(
            AdministratorId::fromString($standing->administratorId),
        );

        return new GrantedPrivilegesView($grants->privileges()->toNames());
    }
}
