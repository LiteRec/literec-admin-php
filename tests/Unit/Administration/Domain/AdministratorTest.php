<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain;

use App\Administration\Domain\Administrator;
use App\Administration\Domain\Event\AdministratorGranted;
use App\Administration\Domain\Event\AdministratorRankChanged;
use App\Administration\Domain\Event\AdministratorRegranted;
use App\Administration\Domain\Event\AdministratorRevoked;
use App\Administration\Domain\Event\RoleAssignedToAdministrator;
use App\Administration\Domain\Event\RoleUnassignedFromAdministrator;
use App\Administration\Domain\Exception\AdministratorAlreadyActive;
use App\Administration\Domain\Exception\AdministratorAlreadyRevoked;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorStanding;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RevocationReason;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\SignInAccountId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class AdministratorTest extends TestCase
{
    private const string ADMINISTRATOR_ID = '019571bf-5d51-7000-b500-00000000ae01';
    private const string SIGN_IN_ACCOUNT_ID = '019571bf-5d51-7000-b500-00000000ae02';
    private const string RANK_ID = '019571bf-5d51-7000-b500-00000000ae03';
    private const string OTHER_RANK_ID = '019571bf-5d51-7000-b500-00000000ae04';
    private const string TENURE_ID = '019571bf-5d51-7000-b500-00000000ae05';
    private const string SECOND_TENURE_ID = '019571bf-5d51-7000-b500-00000000ae06';
    private const string ROLE_ID = '019571bf-5d51-7000-b500-00000000ae07';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    #[Test]
    #[TestDox('grant() opens a tenure, records AdministratorGranted, and sets standing to Active.')]
    public function grant_opens_a_tenure_and_records_event(): void
    {
        $actor = Actor::system();

        $administrator = $this->grantAdministrator(actor: $actor);

        $events = $administrator->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AdministratorGranted::class, $events[0]);
        self::assertTrue($events[0]->grantedBy->equals($actor));
        self::assertSame(AdministratorStanding::Active, $administrator->standing());
        self::assertTrue($administrator->isActive());
        self::assertCount(1, $administrator->tenures());
        self::assertTrue($administrator->tenures()[0]->isOpen());
    }

    #[Test]
    #[TestDox('revoke() closes the open tenure with the acting actor, reason, and timestamp.')]
    public function revoke_closes_the_open_tenure(): void
    {
        $administrator = $this->grantAdministrator();
        $administrator->releaseEvents();
        $actor = Actor::system();
        $reason = RevocationReason::of('Left the organization.');

        $administrator->revoke($reason, $actor, $this->clock);

        self::assertSame(AdministratorStanding::Revoked, $administrator->standing());
        self::assertFalse($administrator->isActive());
        $tenure = $administrator->tenures()[0];
        self::assertFalse($tenure->isOpen());
        self::assertTrue($tenure->revokedBy()?->equals($actor));
        self::assertTrue($tenure->revocationReason()?->equals($reason));

        $events = $administrator->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AdministratorRevoked::class, $events[0]);
        self::assertTrue($events[0]->revokedBy->equals($actor));
        self::assertTrue($events[0]->reason->equals($reason));
    }

    #[Test]
    #[TestDox('revoke() throws AdministratorAlreadyRevoked on a second call.')]
    public function revoke_throws_when_already_revoked(): void
    {
        $administrator = $this->grantAdministrator();
        $administrator->revoke(RevocationReason::of('First reason.'), Actor::system(), $this->clock);
        $administrator->releaseEvents();

        $this->expectException(AdministratorAlreadyRevoked::class);

        $administrator->revoke(RevocationReason::of('Second reason.'), Actor::system(), $this->clock);
    }

    #[Test]
    #[TestDox('regrant() opens a new tenure and leaves the previously closed tenure intact.')]
    public function regrant_opens_new_tenure_and_preserves_closed_one(): void
    {
        $administrator = $this->grantAdministrator();
        $administrator->revoke(RevocationReason::of('Left the organization.'), Actor::system(), $this->clock);
        $closedTenure = $administrator->tenures()[0];
        $administrator->releaseEvents();
        $actor = Actor::system();

        $administrator->regrant(AdministratorTenureId::fromString(self::SECOND_TENURE_ID), $actor, $this->clock);

        self::assertSame(AdministratorStanding::Active, $administrator->standing());
        self::assertTrue($administrator->isActive());
        $tenures = $administrator->tenures();
        self::assertCount(2, $tenures);
        self::assertFalse($tenures[0]->isOpen());
        self::assertSame($closedTenure->revokedAt(), $tenures[0]->revokedAt());
        self::assertTrue($tenures[1]->isOpen());

        $events = $administrator->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AdministratorRegranted::class, $events[0]);
        self::assertTrue($events[0]->grantedBy->equals($actor));
    }

    #[Test]
    #[TestDox('regrant() throws AdministratorAlreadyActive when the administrator is already active.')]
    public function regrant_throws_when_already_active(): void
    {
        $administrator = $this->grantAdministrator();
        $administrator->releaseEvents();

        $this->expectException(AdministratorAlreadyActive::class);

        $administrator->regrant(
            AdministratorTenureId::fromString(self::SECOND_TENURE_ID),
            Actor::system(),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('changeRankTo() updates the rank and records AdministratorRankChanged with the previous and new rank.')]
    public function change_rank_to_updates_rank_and_records_event(): void
    {
        $administrator = $this->grantAdministrator();
        $administrator->releaseEvents();
        $actor = Actor::system();
        $newRankId = RankId::fromString(self::OTHER_RANK_ID);

        $administrator->changeRankTo($newRankId, $actor, $this->clock);

        self::assertTrue($administrator->rankId()->equals($newRankId));
        $events = $administrator->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AdministratorRankChanged::class, $events[0]);
        self::assertTrue($events[0]->previousRankId->equals(RankId::fromString(self::RANK_ID)));
        self::assertTrue($events[0]->newRankId->equals($newRankId));
        self::assertTrue($events[0]->changedBy->equals($actor));
    }

    #[Test]
    #[TestDox('changeRankTo() is a no-op (records no event) when the rank is unchanged.')]
    public function change_rank_to_is_a_no_op_when_unchanged(): void
    {
        $administrator = $this->grantAdministrator();
        $administrator->releaseEvents();

        $administrator->changeRankTo(RankId::fromString(self::RANK_ID), Actor::system(), $this->clock);

        self::assertSame([], $administrator->releaseEvents());
    }

    #[Test]
    #[TestDox('assignRole() adds the role and records RoleAssignedToAdministrator with the acting actor.')]
    public function assign_role_adds_role_and_records_event(): void
    {
        $administrator = $this->grantAdministrator();
        $administrator->releaseEvents();
        $actor = Actor::system();
        $roleId = RoleId::fromString(self::ROLE_ID);

        $administrator->assignRole($roleId, $actor, $this->clock);

        self::assertTrue($administrator->assignedRoles()->contains($roleId));
        $events = $administrator->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleAssignedToAdministrator::class, $events[0]);
        self::assertTrue($events[0]->roleId->equals($roleId));
        self::assertTrue($events[0]->assignedBy->equals($actor));
    }

    #[Test]
    #[TestDox('assignRole() is a no-op (records no event) when the role is already assigned.')]
    public function assign_role_is_a_no_op_when_already_assigned(): void
    {
        $administrator = $this->grantAdministrator();
        $roleId = RoleId::fromString(self::ROLE_ID);
        $administrator->assignRole($roleId, Actor::system(), $this->clock);
        $administrator->releaseEvents();

        $administrator->assignRole($roleId, Actor::system(), $this->clock);

        self::assertSame([], $administrator->releaseEvents());
    }

    #[Test]
    #[TestDox('unassignRole() removes the role and records RoleUnassignedFromAdministrator with the acting actor.')]
    public function unassign_role_removes_role_and_records_event(): void
    {
        $administrator = $this->grantAdministrator();
        $roleId = RoleId::fromString(self::ROLE_ID);
        $administrator->assignRole($roleId, Actor::system(), $this->clock);
        $administrator->releaseEvents();
        $actor = Actor::system();

        $administrator->unassignRole($roleId, $actor, $this->clock);

        self::assertFalse($administrator->assignedRoles()->contains($roleId));
        $events = $administrator->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleUnassignedFromAdministrator::class, $events[0]);
        self::assertTrue($events[0]->roleId->equals($roleId));
        self::assertTrue($events[0]->unassignedBy->equals($actor));
    }

    #[Test]
    #[TestDox('unassignRole() is a no-op (records no event) when the role is not assigned.')]
    public function unassign_role_is_a_no_op_when_not_assigned(): void
    {
        $administrator = $this->grantAdministrator();
        $administrator->releaseEvents();

        $administrator->unassignRole(RoleId::fromString(self::ROLE_ID), Actor::system(), $this->clock);

        self::assertSame([], $administrator->releaseEvents());
    }

    private function grantAdministrator(?Actor $actor = null): Administrator
    {
        return Administrator::grant(
            AdministratorId::fromString(self::ADMINISTRATOR_ID),
            SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_ID),
            RankId::fromString(self::RANK_ID),
            AdministratorTenureId::fromString(self::TENURE_ID),
            $actor ?? Actor::system(),
            $this->clock,
        );
    }
}
