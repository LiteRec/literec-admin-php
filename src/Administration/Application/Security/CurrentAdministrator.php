<?php

declare(strict_types=1);

namespace App\Administration\Application\Security;

use App\Administration\Application\Query\View\AdministratorStandingView;

/**
 * The single port the rest of the application asks whether the currently
 * authenticated sign-in account is presently a member of staff. LRA-270's
 * voter resolves privileges through this port; no other path may answer
 * the same question.
 *
 * Returns null both for an unauthenticated request and for an
 * authenticated sign-in account with no administrator record — from a
 * caller's perspective both mean "not currently staff". A revoked
 * administrator is distinct from both: it returns a non-null view whose
 * standing is {@see \App\Administration\Domain\ValueObject\AdministratorStanding::Revoked}'s
 * value, same contract as {@see \App\Administration\Application\Query\Port\AdministratorStandingReadModel}.
 *
 * Implementations must memoise their answer for the lifetime of the
 * current request only — never across requests, and never in a shared
 * cache — so that revoking an administrator takes effect starting with
 * the very next request. See {@see \App\Administration\Infrastructure\Security\SecurityCurrentAdministrator}
 * for how that boundary is actually held.
 */
interface CurrentAdministrator
{
    public function standing(): ?AdministratorStandingView;
}
