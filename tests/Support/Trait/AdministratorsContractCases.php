<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Administration\Domain\Administrator;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\Exception\AdministratorNotFound;
use App\Administration\Domain\Exception\SignInAccountAlreadyAnAdministrator;
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
 * Shared behavioural contract for any {@see Administrators} adapter. The
 * InMemory and Doctrine drivers both pin to this trait so the
 * implementations cannot drift.
 */
trait AdministratorsContractCases
{
    private const string ADMINISTRATOR_A = '019571bf-5d51-7000-b500-00000000af01';
    private const string ADMINISTRATOR_B = '019571bf-5d51-7000-b500-00000000af02';
    private const string SIGN_IN_ACCOUNT_A = '019571bf-5d51-7000-b500-00000000af03';
    private const string SIGN_IN_ACCOUNT_B = '019571bf-5d51-7000-b500-00000000af04';
    private const string RANK_ID = '019571bf-5d51-7000-b500-00000000af05';
    private const string TENURE_A = '019571bf-5d51-7000-b500-00000000af06';
    private const string TENURE_B = '019571bf-5d51-7000-b500-00000000af07';
    private const string ROLE_ID = '019571bf-5d51-7000-b500-00000000af08';

    abstract protected function administrators(): Administrators;

    abstract protected function clock(): MockClock;

    /**
     * Adapter-specific hook run between a write and the read that
     * verifies it, so the assertion exercises a genuine round trip
     * rather than Doctrine's identity map handing back the same
     * in-memory object it was given. A no-op for InMemoryAdministrators,
     * which has no identity map to reset; the Doctrine adapter clears
     * its EntityManager.
     */
    abstract protected function resetPersistenceContext(): void;

    #[Test]
    #[TestDox('add() then byId() round-trips the sign-in account, rank, standing, and initial tenure.')]
    public function add_then_by_id_round_trips(): void
    {
        $this->seedAdministrator(self::ADMINISTRATOR_A, self::SIGN_IN_ACCOUNT_A, self::TENURE_A);
        $this->resetPersistenceContext();

        $loaded = $this->administrators()->byId(AdministratorId::fromString(self::ADMINISTRATOR_A));
        self::assertSame(self::SIGN_IN_ACCOUNT_A, $loaded->signInAccountId()->value);
        self::assertSame(self::RANK_ID, $loaded->rankId()->value);
        self::assertSame(AdministratorStanding::Active, $loaded->standing());
        self::assertCount(1, $loaded->tenures());
        self::assertTrue($loaded->tenures()[0]->isOpen());
    }

    #[Test]
    #[TestDox('byId() throws AdministratorNotFound when no administrator has that id.')]
    public function by_id_throws_when_missing(): void
    {
        $this->expectException(AdministratorNotFound::class);
        $this->administrators()->byId(AdministratorId::fromString(self::ADMINISTRATOR_A));
    }

    #[Test]
    #[TestDox('forSignInAccount() returns the matching administrator.')]
    public function for_sign_in_account_returns_matching_administrator(): void
    {
        $this->seedAdministrator(self::ADMINISTRATOR_A, self::SIGN_IN_ACCOUNT_A, self::TENURE_A);
        $this->resetPersistenceContext();

        $loaded = $this->administrators()->forSignInAccount(SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_A));
        self::assertSame(self::ADMINISTRATOR_A, $loaded->id()->value);
    }

    #[Test]
    #[TestDox('forSignInAccount() throws AdministratorNotFound when no administrator matches.')]
    public function for_sign_in_account_throws_when_missing(): void
    {
        $this->expectException(AdministratorNotFound::class);
        $this->administrators()->forSignInAccount(SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_A));
    }

    #[Test]
    #[TestDox('existsForSignInAccount() reports whether the sign-in account has an administrator record.')]
    public function exists_for_sign_in_account_reports_membership(): void
    {
        $this->seedAdministrator(self::ADMINISTRATOR_A, self::SIGN_IN_ACCOUNT_A, self::TENURE_A);

        self::assertTrue(
            $this->administrators()->existsForSignInAccount(SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_A)),
        );
        self::assertFalse(
            $this->administrators()->existsForSignInAccount(SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_B)),
        );
    }

    #[Test]
    #[TestDox('add() throws SignInAccountAlreadyAnAdministrator when the sign-in account already has a record.')]
    public function add_throws_on_duplicate_sign_in_account(): void
    {
        $this->seedAdministrator(self::ADMINISTRATOR_A, self::SIGN_IN_ACCOUNT_A, self::TENURE_A);

        $this->expectException(SignInAccountAlreadyAnAdministrator::class);
        $this->seedAdministrator(self::ADMINISTRATOR_B, self::SIGN_IN_ACCOUNT_A, self::TENURE_B);
    }

    #[Test]
    #[TestDox('save() persists revoke, regrant, rank-change, and role-assignment mutations across reloads.')]
    public function save_persists_mutations(): void
    {
        $this->seedAdministrator(self::ADMINISTRATOR_A, self::SIGN_IN_ACCOUNT_A, self::TENURE_A);

        $loaded = $this->administrators()->byId(AdministratorId::fromString(self::ADMINISTRATOR_A));
        $loaded->revoke(RevocationReason::of('Left the organization.'), Actor::system(), $this->clock());
        $loaded->regrant(AdministratorTenureId::fromString(self::TENURE_B), Actor::system(), $this->clock());
        $loaded->assignRole(RoleId::fromString(self::ROLE_ID), Actor::system(), $this->clock());
        $loaded->releaseEvents();
        $this->administrators()->save($loaded);
        $this->resetPersistenceContext();

        $reloaded = $this->administrators()->byId(AdministratorId::fromString(self::ADMINISTRATOR_A));
        self::assertSame(AdministratorStanding::Active, $reloaded->standing());
        self::assertCount(2, $reloaded->tenures());
        self::assertFalse($reloaded->tenures()[0]->isOpen());
        self::assertTrue($reloaded->tenures()[1]->isOpen());
        self::assertTrue($reloaded->assignedRoles()->contains(RoleId::fromString(self::ROLE_ID)));
    }

    #[Test]
    #[TestDox('save() throws AdministratorNotFound when the administrator was never added.')]
    public function save_throws_when_not_added(): void
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

        $this->expectException(AdministratorNotFound::class);
        $this->administrators()->save($administrator);
    }

    #[Test]
    #[TestDox('activeHoldersOfRole() returns only active administrators with the role directly assigned.')]
    public function active_holders_of_role_returns_only_active_direct_assignees(): void
    {
        $roleId = RoleId::fromString(self::ROLE_ID);

        $holder = $this->seedAdministrator(self::ADMINISTRATOR_A, self::SIGN_IN_ACCOUNT_A, self::TENURE_A);
        $holder->assignRole($roleId, Actor::system(), $this->clock());
        $holder->releaseEvents();
        $this->administrators()->save($holder);

        $revokedHolder = $this->seedAdministrator(self::ADMINISTRATOR_B, self::SIGN_IN_ACCOUNT_B, self::TENURE_B);
        $revokedHolder->assignRole($roleId, Actor::system(), $this->clock());
        $revokedHolder->revoke(RevocationReason::of('Left.'), Actor::system(), $this->clock());
        $revokedHolder->releaseEvents();
        $this->administrators()->save($revokedHolder);
        $this->resetPersistenceContext();

        $result = $this->administrators()->activeHoldersOfRole($roleId);
        $ids = array_map(static fn (Administrator $a): string => $a->id()->value, $result);

        self::assertSame([self::ADMINISTRATOR_A], $ids);
    }

    /**
     * Deliberately does not call {@see self::resetPersistenceContext()} —
     * some callers mutate and save() the returned Administrator directly,
     * and Doctrine's save() requires the instance it is given to still be
     * managed. Callers that need a genuine round trip reset explicitly,
     * between this call and the verifying read.
     */
    private function seedAdministrator(string $id, string $signInAccountId, string $tenureId): Administrator
    {
        $administrator = Administrator::grant(
            AdministratorId::fromString($id),
            SignInAccountId::fromString($signInAccountId),
            RankId::fromString(self::RANK_ID),
            AdministratorTenureId::fromString($tenureId),
            Actor::system(),
            $this->clock(),
        );
        $administrator->releaseEvents();
        $this->administrators()->add($administrator);

        return $administrator;
    }
}
