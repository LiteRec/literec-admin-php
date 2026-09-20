<?php

declare(strict_types=1);

namespace App\Administration\Application\Query\View;

/**
 * Projection of one sign-in account's current staff standing: whether it
 * is presently an administrator, which rank it holds, and which roles are
 * directly assigned to it. This is the single per-request read
 * {@see \App\Administration\Application\Security\CurrentAdministrator}
 * resolves and LRA-270 builds its privilege resolution on.
 *
 * A non-null view is NOT sufficient on its own to conclude the account is
 * currently staff: a revoked administrator still resolves to a populated
 * view (see {@see \App\Administration\Application\Query\Port\AdministratorStandingReadModel}'s
 * docblock), carrying its retained rankId/roleIds so history/audit
 * consumers can still see them. A caller deciding "may this account act
 * as staff right now" must check {@see self::isActive()}, not merely
 * "is the view null".
 *
 * $standing carries {@see \App\Administration\Domain\ValueObject\AdministratorStanding}'s
 * primitive value, not the enum itself — view DTOs in this codebase are
 * primitive-only, same treatment as RoleSummaryView/RoleDetailView, and
 * PHPat's commandAndQueryDtosDoNotDependOnDomain rule forbids importing
 * the enum here even just to compare against it — see ACTIVE_VALUE.
 */
final readonly class AdministratorStandingView
{
    /**
     * Mirrors {@see \App\Administration\Domain\ValueObject\AdministratorStanding::Active}'s
     * backing value as a bare string literal rather than importing the
     * enum, which this DTO's primitive-only contract forbids.
     */
    private const string ACTIVE_VALUE = 'ACTIVE';

    /**
     * @param list<string> $roleIds
     */
    public function __construct(
        public string $administratorId,
        public string $standing,
        public string $rankId,
        public array $roleIds,
    ) {
    }

    /**
     * Mirrors {@see \App\Administration\Domain\Administrator::isActive()}
     * so a caller cannot mistake a non-null, but revoked, view for "is
     * staff" — see this class's docblock.
     */
    public function isActive(): bool
    {
        return $this->standing === self::ACTIVE_VALUE;
    }
}
