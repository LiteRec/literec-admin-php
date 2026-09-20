<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

/**
 * The kind of thing that contributed a {@see PrivilegeGrant} to an
 * administrator's effective privilege set (LRA-270).
 *
 * Two cases ship with this slice; LRA-271 adds SupportAccessGrant for
 * time-boxed vendor support access. Adding a case here never requires
 * editing {@see \App\Administration\Infrastructure\Security\UnionOfGrantSources}
 * or the voter — a new origin arrives together with a new
 * {@see PrivilegeGrantSource} implementation, registered once via the
 * `administration.privilege_grant_source` DI tag.
 */
enum GrantOrigin: string
{
    /**
     * The privilege was granted through a role carried by the
     * administrator's rank (see {@see \App\Administration\Infrastructure\Persistence\Doctrine\Read\RankRoleGrants}).
     */
    case RankRole = 'RANK_ROLE';

    /**
     * The privilege was granted through a role assigned directly to the
     * administrator, bypassing their rank (see
     * {@see \App\Administration\Infrastructure\Persistence\Doctrine\Read\DirectRoleGrants}).
     */
    case DirectRole = 'DIRECT_ROLE';
}
