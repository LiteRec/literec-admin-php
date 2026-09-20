<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Infrastructure\Security;

use App\Administration\Domain\Administrator;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
use App\Administration\Domain\ValueObject\GrantOrigin;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RevocationReason;
use App\Administration\Domain\ValueObject\SignInAccountId;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryAdministrators;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryAdministratorStandingReadModel;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryPrivilegeGrantSource;
use App\Administration\Infrastructure\Security\UnionOfGrantSources;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class UnionOfGrantSourcesTest extends TestCase
{
    private const string ADMINISTRATOR_A = '019571bf-5d51-7000-b500-00000000ae01';
    private const string SIGN_IN_ACCOUNT_A = '019571bf-5d51-7000-b500-00000000ae02';
    private const string RANK_A = '019571bf-5d51-7000-b500-00000000ae04';
    private const string TENURE_A = '019571bf-5d51-7000-b500-00000000ae05';

    private InMemoryAdministrators $administrators;
    private InMemoryAdministratorStandingReadModel $standingReadModel;

    protected function setUp(): void
    {
        $this->administrators = new InMemoryAdministrators();
        $this->standingReadModel = new InMemoryAdministratorStandingReadModel($this->administrators);
    }

    #[Test]
    #[TestDox('forAdministrator() unions grants from every registered source, no source having contributed nothing.')]
    public function unions_grants_from_every_source(): void
    {
        $this->seedActiveAdministrator();

        $sourceA = new InMemoryPrivilegeGrantSource();
        $sourceA->grant(
            AdministratorId::fromString(self::ADMINISTRATOR_A),
            Privilege::ViewUsers,
            GrantOrigin::RankRole,
            'role-1',
            'Front Desk',
        );
        $sourceB = new InMemoryPrivilegeGrantSource();
        $sourceB->grant(
            AdministratorId::fromString(self::ADMINISTRATOR_A),
            Privilege::AddUsers,
            GrantOrigin::DirectRole,
            'role-2',
            'Backup Cashier',
        );
        $emptySource = new InMemoryPrivilegeGrantSource();

        $union = new UnionOfGrantSources([$sourceA, $sourceB, $emptySource], $this->standingReadModel);

        $grants = $union->forAdministrator(AdministratorId::fromString(self::ADMINISTRATOR_A));

        self::assertSame(
            [Privilege::ViewUsers->value, Privilege::AddUsers->value],
            $grants->privileges()->toNames(),
        );
    }

    #[Test]
    #[TestDox('forAdministrator() resolves to an empty set when zero sources are registered.')]
    public function resolves_to_empty_set_with_no_sources(): void
    {
        $this->seedActiveAdministrator();

        $union = new UnionOfGrantSources([], $this->standingReadModel);

        $grants = $union->forAdministrator(AdministratorId::fromString(self::ADMINISTRATOR_A));

        self::assertSame(0, $grants->count());
    }

    #[Test]
    #[TestDox('forAdministrator() resolves to an empty set for a revoked administrator without asking any source.')]
    public function resolves_to_empty_set_for_a_revoked_administrator(): void
    {
        $administrator = $this->seedActiveAdministrator();
        $administrator->revoke(
            RevocationReason::of('Left the organization.'),
            Actor::system(),
            new MockClock(new DateTimeImmutable('2026-05-27 12:00:00')),
        );
        $administrator->releaseEvents();
        $this->administrators->save($administrator);

        $source = new InMemoryPrivilegeGrantSource();
        $source->grant(
            AdministratorId::fromString(self::ADMINISTRATOR_A),
            Privilege::ViewUsers,
            GrantOrigin::RankRole,
            'role-1',
            'Front Desk',
        );

        $union = new UnionOfGrantSources([$source], $this->standingReadModel);

        $grants = $union->forAdministrator(AdministratorId::fromString(self::ADMINISTRATOR_A));

        self::assertSame(0, $grants->count());
    }

    #[Test]
    #[TestDox('forAdministrator() resolves to an empty set for an id with no administrator record at all.')]
    public function resolves_to_empty_set_for_an_unknown_administrator(): void
    {
        $union = new UnionOfGrantSources([], $this->standingReadModel);

        $grants = $union->forAdministrator(AdministratorId::fromString(self::ADMINISTRATOR_A));

        self::assertSame(0, $grants->count());
    }

    private function seedActiveAdministrator(): Administrator
    {
        $clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
        $administrator = Administrator::grant(
            AdministratorId::fromString(self::ADMINISTRATOR_A),
            SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_A),
            RankId::fromString(self::RANK_A),
            AdministratorTenureId::fromString(self::TENURE_A),
            Actor::system(),
            $clock,
        );
        $administrator->releaseEvents();
        $this->administrators->add($administrator);

        return $administrator;
    }
}
