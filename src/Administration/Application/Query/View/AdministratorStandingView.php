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
 * $standing carries {@see \App\Administration\Domain\ValueObject\AdministratorStanding}'s
 * primitive value, not the enum itself — view DTOs in this codebase are
 * primitive-only, same treatment as RoleSummaryView/RoleDetailView.
 */
final readonly class AdministratorStandingView
{
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
}
