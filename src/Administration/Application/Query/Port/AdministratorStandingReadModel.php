<?php

declare(strict_types=1);

namespace App\Administration\Application\Query\Port;

use App\Administration\Application\Query\View\AdministratorStandingView;
use App\Administration\Domain\ValueObject\AdministratorId;
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

    /**
     * The same question as {@see self::standingFor()}, keyed by
     * {@see AdministratorId} rather than sign-in account, for
     * {@see \App\Administration\Infrastructure\Security\UnionOfGrantSources}
     * (LRA-270): it already holds the administrator identity — obtained
     * from {@see \App\Administration\Application\Security\CurrentAdministrator}
     * before any {@see \App\Administration\Domain\PrivilegeGrantSource}
     * runs — and consulting this read-side port again (rather than the
     * write-side {@see \App\Administration\Domain\Administrators}
     * aggregate repository) keeps that per-request check off the
     * EntityManager's hydrator/UnitOfWork path, same performance
     * rationale as {@see self::standingFor()} itself.
     *
     * Returns null when no administrator has this id — practically
     * unreachable for a caller that only ever supplies an id it just
     * read from this same read model, but kept nullable rather than
     * throwing so a caller never needs to catch an exception for what
     * is, from its perspective, simply "no active grants".
     */
    public function standingOfAdministrator(AdministratorId $administratorId): ?AdministratorStandingView;
}
