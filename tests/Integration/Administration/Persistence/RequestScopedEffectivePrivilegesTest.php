<?php

declare(strict_types=1);

namespace App\Tests\Integration\Administration\Persistence;

use App\Administration\Domain\Administrator;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\EffectivePrivileges;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\Rank;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\Role;
use App\Administration\Domain\Roles;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleDescription;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use App\Administration\Domain\ValueObject\SignInAccountId;
use App\Administration\Infrastructure\Security\RequestScopedEffectivePrivileges;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Proves the memoisation boundary {@see RequestScopedEffectivePrivileges}
 * exists for: a second call within one request (here, one container
 * instance) is memoised even after the underlying grant changes, and
 * {@see RequestScopedEffectivePrivileges::reset()} — the method
 * `kernel.terminate` calls in production — drops the memo so the next
 * call resolves fresh. The end-to-end "the next HTTP request sees it"
 * proof lives in the Functional-tier RoleRevocationTakesEffectOnNextRequestTest.
 */
#[Medium]
final class RequestScopedEffectivePrivilegesTest extends KernelTestCase
{
    private const string ADMINISTRATOR_A = '019571bf-5d51-7000-b500-00000000ae01';
    private const string SIGN_IN_ACCOUNT_A = '019571bf-5d51-7000-b500-00000000ae02';
    private const string RANK_A = '019571bf-5d51-7000-b500-00000000ae04';
    private const string TENURE_A = '019571bf-5d51-7000-b500-00000000ae05';
    private const string ROLE_A = '019571bf-5d51-7000-b500-00000000fc01';

    #[Test]
    #[TestDox('forAdministrator() memoises within one instance; reset() drops the memo so the next call is fresh.')]
    public function memoises_until_reset(): void
    {
        self::bootKernel();
        $clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));

        $roles = static::getContainer()->get(Roles::class);
        self::assertInstanceOf(Roles::class, $roles);
        $ranks = static::getContainer()->get(Ranks::class);
        self::assertInstanceOf(Ranks::class, $ranks);
        $administrators = static::getContainer()->get(Administrators::class);
        self::assertInstanceOf(Administrators::class, $administrators);

        $rank = Rank::define(
            RankId::fromString(self::RANK_A),
            RankName::of('Memoisation Fixture Rank'),
            SeniorityLevel::of(50),
            AssignedRoles::none(),
            Actor::system(),
            $clock,
        );
        $ranks->add($rank);
        $rank->releaseEvents();

        $administrator = Administrator::grant(
            AdministratorId::fromString(self::ADMINISTRATOR_A),
            SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_A),
            RankId::fromString(self::RANK_A),
            AdministratorTenureId::fromString(self::TENURE_A),
            Actor::system(),
            $clock,
        );
        $administrator->releaseEvents();
        $administrators->add($administrator);

        $effectivePrivileges = static::getContainer()->get(EffectivePrivileges::class);
        self::assertInstanceOf(RequestScopedEffectivePrivileges::class, $effectivePrivileges);

        $administratorId = AdministratorId::fromString(self::ADMINISTRATOR_A);

        self::assertSame(0, $effectivePrivileges->forAdministrator($administratorId)->count());

        // Grant a role carrying a privilege to the rank AFTER the first
        // resolution above — this must NOT show up on the very next call
        // if memoisation is real.
        $role = Role::define(
            RoleId::fromString(self::ROLE_A),
            RoleName::of('Front Desk'),
            RoleDescription::empty(),
            PrivilegeSet::of(Privilege::ViewUsers),
            Actor::system(),
            $clock,
        );
        $role->releaseEvents();
        $roles->add($role);

        $rank->grantRole(RoleId::fromString(self::ROLE_A), Actor::system(), $clock);
        $ranks->save($rank);
        $rank->releaseEvents();

        self::assertSame(
            0,
            $effectivePrivileges->forAdministrator($administratorId)->count(),
            'A second call on the same instance must still see the memoised (empty) result.',
        );

        $effectivePrivileges->reset();

        self::assertTrue(
            $effectivePrivileges->forAdministrator($administratorId)->privileges()->contains(Privilege::ViewUsers),
            'After reset(), the next call must resolve fresh and see the newly granted privilege.',
        );
    }
}
