<?php

declare(strict_types=1);

namespace App\Tests\Integration\Administration\Persistence\Read;

use App\Administration\Domain\Administrator;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\PrivilegeGrantSource;
use App\Administration\Domain\Rank;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\Role;
use App\Administration\Domain\Roles;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\GrantOrigin;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleDescription;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use App\Administration\Domain\ValueObject\SignInAccountId;
use App\Administration\Infrastructure\Persistence\Doctrine\Read\RankRoleGrants;
use App\Tests\Support\Trait\PrivilegeGrantSourceContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the real Doctrine DBAL {@see RankRoleGrants} source: an
 * administrator holds a privilege through whichever roles the rank
 * carries, and this test seeds through the write-side Roles/Ranks/
 * Administrators repositories so the migration-defined join tables
 * receive the data the source then queries via raw SQL.
 */
#[Medium]
final class RankRoleGrantsTest extends KernelTestCase
{
    use PrivilegeGrantSourceContractCases;

    private const string ADMINISTRATOR_A = '019571bf-5d51-7000-b500-00000000ae01';
    private const string SIGN_IN_ACCOUNT_A = '019571bf-5d51-7000-b500-00000000ae02';
    private const string RANK_A = '019571bf-5d51-7000-b500-00000000ae04';
    private const string TENURE_A = '019571bf-5d51-7000-b500-00000000ae05';

    private MockClock $clock;
    private Roles $roles;
    private Ranks $ranks;
    private Administrators $administrators;
    private Rank $rank;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));

        $roles = static::getContainer()->get(Roles::class);
        self::assertInstanceOf(Roles::class, $roles);
        $this->roles = $roles;

        $ranks = static::getContainer()->get(Ranks::class);
        self::assertInstanceOf(Ranks::class, $ranks);
        $this->ranks = $ranks;

        $administrators = static::getContainer()->get(Administrators::class);
        self::assertInstanceOf(Administrators::class, $administrators);
        $this->administrators = $administrators;

        $this->rank = Rank::define(
            RankId::fromString(self::RANK_A),
            RankName::of('Grants Fixture Rank'),
            SeniorityLevel::of(50),
            AssignedRoles::none(),
            Actor::system(),
            $this->clock,
        );
        $this->ranks->add($this->rank);
        $this->rank->releaseEvents();

        $administrator = Administrator::grant(
            AdministratorId::fromString(self::ADMINISTRATOR_A),
            SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_A),
            RankId::fromString(self::RANK_A),
            AdministratorTenureId::fromString(self::TENURE_A),
            Actor::system(),
            $this->clock,
        );
        $administrator->releaseEvents();
        $this->administrators->add($administrator);
    }

    protected function source(): PrivilegeGrantSource
    {
        $source = static::getContainer()->get(RankRoleGrants::class);
        self::assertInstanceOf(RankRoleGrants::class, $source);

        return $source;
    }

    protected function administratorId(): AdministratorId
    {
        return AdministratorId::fromString(self::ADMINISTRATOR_A);
    }

    protected function expectedOrigin(): GrantOrigin
    {
        return GrantOrigin::RankRole;
    }

    protected function grantPrivilege(Privilege $privilege, string $sourceId, string $sourceName): void
    {
        $role = Role::define(
            RoleId::fromString($sourceId),
            RoleName::of($sourceName),
            RoleDescription::empty(),
            PrivilegeSet::of($privilege),
            Actor::system(),
            $this->clock,
        );
        $role->releaseEvents();
        $this->roles->add($role);

        $this->rank->grantRole(RoleId::fromString($sourceId), Actor::system(), $this->clock);
        // Ranks::save() reads the pending RoleGrantedToRank event to
        // seed administration_rank_roles — releaseEvents() must run
        // after save(), same order GrantRoleToRankHandler uses.
        $this->ranks->save($this->rank);
        $this->rank->releaseEvents();
    }
}
