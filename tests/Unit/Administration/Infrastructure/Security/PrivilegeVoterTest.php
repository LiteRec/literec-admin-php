<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Infrastructure\Security;

use App\Administration\Application\Query\Port\AdministratorStandingReadModel;
use App\Administration\Application\Query\View\AdministratorStandingView;
use App\Administration\Application\Security\CurrentAdministrator;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorStanding;
use App\Administration\Domain\ValueObject\GrantOrigin;
use App\Administration\Domain\ValueObject\SignInAccountId;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryPrivilegeGrantSource;
use App\Administration\Infrastructure\Security\PrivilegeVoter;
use App\Administration\Infrastructure\Security\UnionOfGrantSources;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Exercises {@see PrivilegeVoter} entirely in memory: a fake
 * {@see CurrentAdministrator} standing in for LRA-279's port, and
 * {@see UnionOfGrantSources} over an {@see InMemoryPrivilegeGrantSource}
 * standing in for the real Doctrine-backed sources — no database.
 */
#[Small]
final class PrivilegeVoterTest extends TestCase
{
    private const string ADMINISTRATOR_A = '019571bf-5d51-7000-b500-00000000ae01';

    #[Test]
    #[TestDox('supportsAttribute() is true for a catalogue privilege name.')]
    public function supports_a_catalogue_privilege(): void
    {
        $voter = $this->voterFor(null, null);

        self::assertTrue($voter->supportsAttribute(Privilege::ViewUsers->value));
    }

    #[Test]
    #[TestDox('supportsAttribute() is true for an unknown privilege-shaped string, denied loudly not abstained.')]
    public function supports_a_privilege_shaped_unknown_string(): void
    {
        $voter = $this->voterFor(null, null);

        self::assertTrue($voter->supportsAttribute('NOT_A_REAL_PRIVILEGE'));
    }

    #[Test]
    #[TestDox('supportsAttribute() is false for a ROLE_* attribute, even though it is uppercase-snake shaped.')]
    public function does_not_support_a_role_attribute(): void
    {
        $voter = $this->voterFor(null, null);

        self::assertFalse($voter->supportsAttribute('ROLE_USER'));
    }

    #[Test]
    #[TestDox('supportsAttribute() is false for a reserved Symfony pseudo-attribute.')]
    public function does_not_support_a_reserved_symfony_attribute(): void
    {
        $voter = $this->voterFor(null, null);

        self::assertFalse($voter->supportsAttribute('PUBLIC_ACCESS'));
        self::assertFalse($voter->supportsAttribute('IS_AUTHENTICATED_FULLY'));
    }

    #[Test]
    #[TestDox('supportsAttribute() is false for a lowercase, non-privilege-shaped attribute.')]
    public function does_not_support_a_lowercase_attribute(): void
    {
        $voter = $this->voterFor(null, null);

        self::assertFalse($voter->supportsAttribute('anonymize_member'));
    }

    #[Test]
    #[TestDox('supportsType() is true unconditionally; subjects are not consulted in this slice.')]
    public function supports_any_subject_type(): void
    {
        $voter = $this->voterFor(null, null);

        self::assertTrue($voter->supportsType('anything'));
    }

    #[Test]
    #[TestDox('vote() abstains when no attribute in the set is a privilege.')]
    public function abstains_on_a_non_privilege_attribute(): void
    {
        $voter = $this->voterFor(null, null);

        $result = $voter->vote($this->createStub(TokenInterface::class), null, ['ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    #[Test]
    #[TestDox('vote() denies, with a reason, an attribute shaped like a privilege but not in the catalogue.')]
    public function denies_an_unknown_privilege_name(): void
    {
        $voter = $this->voterFor(null, null);
        $vote = new Vote();

        $result = $voter->vote($this->createStub(TokenInterface::class), null, ['NOT_A_REAL_PRIVILEGE'], $vote);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
        self::assertNotEmpty($vote->reasons);
    }

    #[Test]
    #[TestDox('vote() denies when the current sign-in account is not currently staff.')]
    public function denies_when_not_currently_staff(): void
    {
        $voter = $this->voterFor(null, null);

        $result = $voter->vote($this->createStub(TokenInterface::class), null, [Privilege::ViewUsers->value]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    #[Test]
    #[TestDox('vote() denies when the administrator holds no grant for the requested privilege.')]
    public function denies_when_privilege_is_not_granted(): void
    {
        $standing = $this->activeStanding();
        $source = new InMemoryPrivilegeGrantSource();

        $voter = $this->voterFor($standing, $source);

        $result = $voter->vote($this->createStub(TokenInterface::class), null, [Privilege::ViewUsers->value]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    #[Test]
    #[TestDox('vote() grants, with a reason naming the origin, when the administrator holds the requested privilege.')]
    public function grants_when_privilege_is_held(): void
    {
        $standing = $this->activeStanding();
        $source = new InMemoryPrivilegeGrantSource();
        $source->grant(
            AdministratorId::fromString(self::ADMINISTRATOR_A),
            Privilege::ViewUsers,
            GrantOrigin::RankRole,
            'role-1',
            'Front Desk',
        );

        $voter = $this->voterFor($standing, $source);
        $vote = new Vote();

        $result = $voter->vote($this->createStub(TokenInterface::class), null, [Privilege::ViewUsers->value], $vote);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
        self::assertNotEmpty($vote->reasons);
    }

    #[Test]
    #[TestDox('vote() denies the whole set when only one of several requested privileges is held.')]
    public function denies_when_any_attribute_in_the_set_is_not_granted(): void
    {
        $standing = $this->activeStanding();
        $source = new InMemoryPrivilegeGrantSource();
        $source->grant(
            AdministratorId::fromString(self::ADMINISTRATOR_A),
            Privilege::ViewUsers,
            GrantOrigin::RankRole,
            'role-1',
            'Front Desk',
        );

        $voter = $this->voterFor($standing, $source);

        $result = $voter->vote(
            $this->createStub(TokenInterface::class),
            null,
            [Privilege::ViewUsers->value, Privilege::ManageAdminRanks->value],
        );

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    #[Test]
    #[TestDox('vote() grants when an unsupported attribute is mixed in alongside a held privilege.')]
    public function grants_when_an_unsupported_attribute_accompanies_a_held_privilege(): void
    {
        $standing = $this->activeStanding();
        $source = new InMemoryPrivilegeGrantSource();
        $source->grant(
            AdministratorId::fromString(self::ADMINISTRATOR_A),
            Privilege::ViewUsers,
            GrantOrigin::RankRole,
            'role-1',
            'Front Desk',
        );

        $voter = $this->voterFor($standing, $source);

        $result = $voter->vote(
            $this->createStub(TokenInterface::class),
            null,
            ['ROLE_USER', Privilege::ViewUsers->value],
        );

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    private function activeStanding(): AdministratorStandingView
    {
        return new AdministratorStandingView(
            self::ADMINISTRATOR_A,
            AdministratorStanding::Active->value,
            '019571bf-5d51-7000-b500-00000000ae04',
            [],
        );
    }

    private function voterFor(
        ?AdministratorStandingView $standing,
        ?InMemoryPrivilegeGrantSource $source,
    ): PrivilegeVoter {
        $currentAdministrator = new class ($standing) implements CurrentAdministrator {
            public function __construct(private readonly ?AdministratorStandingView $standing)
            {
            }

            public function standing(): ?AdministratorStandingView
            {
                return $this->standing;
            }
        };

        $standingReadModel = new class ($standing) implements AdministratorStandingReadModel {
            public function __construct(private readonly ?AdministratorStandingView $standing)
            {
            }

            public function standingFor(SignInAccountId $signInAccountId): ?AdministratorStandingView
            {
                return $this->standing;
            }

            public function standingOfAdministrator(AdministratorId $administratorId): ?AdministratorStandingView
            {
                return $this->standing;
            }
        };

        $sources = $source === null ? [] : [$source];
        $effectivePrivileges = new UnionOfGrantSources($sources, $standingReadModel);

        return new PrivilegeVoter($currentAdministrator, $effectivePrivileges);
    }
}
