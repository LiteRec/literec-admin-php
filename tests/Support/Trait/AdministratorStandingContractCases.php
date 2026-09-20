<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Administration\Application\Query\Port\AdministratorStandingReadModel;
use App\Administration\Domain\Administrator;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorStanding;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RevocationReason;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\SignInAccountId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared behavioural contract for any {@see AdministratorStandingReadModel}
 * adapter. The InMemory and Doctrine drivers both pin to this trait so the
 * implementations cannot drift — same pattern as
 * {@see AdministratorsContractCases}, whose seed fixture ids this trait
 * reuses verbatim to keep both suites' data unambiguous when read side by
 * side.
 */
trait AdministratorStandingContractCases
{
    private const string ADMINISTRATOR_A = '019571bf-5d51-7000-b500-00000000ae01';
    private const string SIGN_IN_ACCOUNT_A = '019571bf-5d51-7000-b500-00000000ae02';
    private const string SIGN_IN_ACCOUNT_B = '019571bf-5d51-7000-b500-00000000ae03';
    private const string RANK_ID = '019571bf-5d51-7000-b500-00000000ae04';
    private const string TENURE_A = '019571bf-5d51-7000-b500-00000000ae05';
    private const string ROLE_ID = '019571bf-5d51-7000-b500-00000000ae06';

    abstract protected function readModel(): AdministratorStandingReadModel;

    abstract protected function administrators(): Administrators;

    abstract protected function clock(): MockClock;

    /**
     * Adapter-specific hook run between a write and the read that
     * verifies it — see {@see AdministratorsContractCases::resetPersistenceContext()}
     * for why. A no-op for the in-memory adapter.
     */
    abstract protected function resetPersistenceContext(): void;

    #[Test]
    #[TestDox('standingFor() returns null when the sign-in account has no administrator record.')]
    public function standing_for_returns_null_when_no_record_exists(): void
    {
        $view = $this->readModel()->standingFor(SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_B));

        self::assertNull($view);
    }

    #[Test]
    #[TestDox('standingFor() reports Active standing, rank id, and assigned role ids for an active administrator.')]
    public function standing_for_reports_active_administrator(): void
    {
        $administrator = $this->seedAdministrator();
        $administrator->assignRole(RoleId::fromString(self::ROLE_ID), Actor::system(), $this->clock());
        $administrator->releaseEvents();
        $this->administrators()->save($administrator);
        $this->resetPersistenceContext();

        $view = $this->readModel()->standingFor(SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_A));

        self::assertNotNull($view);
        self::assertSame(self::ADMINISTRATOR_A, $view->administratorId);
        self::assertSame(AdministratorStanding::Active->value, $view->standing);
        self::assertSame(self::RANK_ID, $view->rankId);
        self::assertSame([self::ROLE_ID], $view->roleIds);
    }

    #[Test]
    #[TestDox('standingFor() reports Revoked standing for a revoked administrator, not null.')]
    public function standing_for_reports_revoked_administrator(): void
    {
        $administrator = $this->seedAdministrator();
        $administrator->revoke(RevocationReason::of('Left the organization.'), Actor::system(), $this->clock());
        $administrator->releaseEvents();
        $this->administrators()->save($administrator);
        $this->resetPersistenceContext();

        $view = $this->readModel()->standingFor(SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_A));

        self::assertNotNull($view);
        self::assertSame(AdministratorStanding::Revoked->value, $view->standing);
    }

    #[Test]
    #[TestDox('standingFor() reports an empty roleIds list for an administrator with no directly assigned roles.')]
    public function standing_for_reports_empty_role_list_when_none_assigned(): void
    {
        $this->seedAdministrator();
        $this->resetPersistenceContext();

        $view = $this->readModel()->standingFor(SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_A));

        self::assertNotNull($view);
        self::assertSame([], $view->roleIds);
    }

    private function seedAdministrator(): Administrator
    {
        $administrator = Administrator::grant(
            AdministratorId::fromString(self::ADMINISTRATOR_A),
            SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_A),
            RankId::fromString(self::RANK_ID),
            AdministratorTenureId::fromString(self::TENURE_A),
            Actor::system(),
            $this->clock(),
        );
        $administrator->releaseEvents();
        $this->administrators()->add($administrator);

        return $administrator;
    }
}
