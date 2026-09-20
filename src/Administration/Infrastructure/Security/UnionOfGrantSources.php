<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Security;

use App\Administration\Application\Query\Port\AdministratorStandingReadModel;
use App\Administration\Domain\EffectivePrivileges;
use App\Administration\Domain\PrivilegeGrantSource;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\PrivilegeGrants;

/**
 * Resolves an administrator's effective privileges as the union of every
 * registered {@see PrivilegeGrantSource} (LRA-270). Contains no
 * knowledge of what any source is — adding a new kind of grant (LRA-271's
 * time-boxed vendor support access) means registering one more
 * `administration.privilege_grant_source`-tagged service, never editing
 * this class.
 *
 * Consults {@see AdministratorStandingReadModel} once, before any source
 * runs: an administrator whose status has been revoked resolves to an
 * empty set here, so no individual source ever needs to re-check
 * standing for itself. Uses the read-side port rather than the write-side
 * {@see \App\Administration\Domain\Administrators} aggregate repository
 * so this per-request check never pays the EntityManager's
 * hydrator/UnitOfWork cost — same rationale as the two Doctrine sources
 * this class composes.
 *
 * Wrapped by {@see RequestScopedEffectivePrivileges} for the production
 * binding; this class itself performs no caching.
 */
final class UnionOfGrantSources implements EffectivePrivileges
{
    /**
     * @param iterable<PrivilegeGrantSource> $sources
     */
    public function __construct(
        private readonly iterable $sources,
        private readonly AdministratorStandingReadModel $standingReadModel,
    ) {
    }

    public function forAdministrator(AdministratorId $id): PrivilegeGrants
    {
        $standing = $this->standingReadModel->standingOfAdministrator($id);

        if ($standing === null || !$standing->isActive()) {
            return PrivilegeGrants::none();
        }

        $grants = PrivilegeGrants::none();
        foreach ($this->sources as $source) {
            $grants = $grants->merge($source->grantsFor($id));
        }

        return $grants;
    }
}
