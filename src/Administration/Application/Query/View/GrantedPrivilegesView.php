<?php

declare(strict_types=1);

namespace App\Administration\Application\Query\View;

/**
 * Projection of the currently authenticated sign-in account's granted
 * privilege names, for {@see \App\Administration\Application\Query\GetGrantedPrivilegesHandler}.
 * Empty for an unauthenticated request, an authenticated account with no
 * administrator record, or a revoked administrator — the read path never
 * distinguishes those from "has no privileges", matching
 * {@see \App\Administration\Application\Security\CurrentAdministrator}'s
 * own null/isActive() contract.
 */
final readonly class GrantedPrivilegesView
{
    /**
     * @param list<string> $privileges
     */
    public function __construct(
        public array $privileges,
    ) {
    }
}
