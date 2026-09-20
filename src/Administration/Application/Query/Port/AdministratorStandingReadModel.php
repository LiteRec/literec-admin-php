<?php

declare(strict_types=1);

namespace App\Administration\Application\Query\Port;

use App\Administration\Application\Query\View\AdministratorStandingView;
use App\Administration\Domain\ValueObject\SignInAccountId;

/**
 * Read-side port answering "is this sign-in account currently a member of
 * staff, and with which rank and roles". This is the ONLY path that
 * question is answered through — {@see \App\Administration\Application\Security\CurrentAdministrator}
 * resolves through this port and nothing else, so LRA-270's "every
 * privilege check has a single implementation" acceptance criterion holds
 * from the moment this ticket merges.
 *
 * Sits in the Application layer because it is not a domain invariant (no
 * aggregate consistency boundary) but a use-case dependency the security
 * adapter injects — same reasoning as {@see RoleReadModel}. The Doctrine
 * adapter queries the same tables the write side (DoctrineAdministrators)
 * writes via direct DBAL SQL (CQRS-lite); no aggregate ever leaves the
 * Application layer.
 */
interface AdministratorStandingReadModel
{
    /**
     * Returns null when $signInAccountId has no administrator record at
     * all — deliberately not distinguished from "has a record but it is
     * revoked", which is instead reported via a non-null view whose
     * standing is {@see \App\Administration\Domain\ValueObject\AdministratorStanding::Revoked}'s
     * value: both are legitimate current states a caller must handle,
     * not error conditions.
     */
    public function standingFor(SignInAccountId $signInAccountId): ?AdministratorStandingView;
}
