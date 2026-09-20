<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Fixtures;

use App\Administration\Application\Command\GrantAdministrator;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RankId;
use App\Shared\Infrastructure\Fixtures\FixtureReferenceRegistry;
use App\Shared\Infrastructure\Fixtures\HandledResult;
use App\Users\Domain\ValueObject\UserId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Grants the admin persona (`App\Users\Infrastructure\Fixtures\UsersFixtures::ADMIN_USERNAME`)
 * staff status, dispatching {@see GrantAdministrator} through the bus —
 * same treatment as {@see AdministrationRolesFixtures}/{@see RankLadderFixtures}.
 * Never touches the EntityManager directly.
 *
 * Reads the admin's minted {@see UserId} and the seeded ladder's
 * "System Administrator" rank id from the fixtures reference registry
 * rather than querying either port directly: Users and Administration
 * are separate bounded contexts, so this fixture has no access to the
 * Users repository, and the registry is how UsersFixtures and
 * {@see RankLadderFixtures} publish those generated ids for a downstream
 * fixture to consume.
 *
 * Deliberately does not `use App\Users\Infrastructure\Fixtures\UsersFixtures`
 * anywhere in this file, including in {@see self::getDependencies()} —
 * that class belongs to the UsersInfrastructure Deptrac layer, which
 * AdministrationInfrastructure is not permitted to depend on (only the
 * narrow UsersPublishedLanguage/UsersSecurityPublishedLanguage slices
 * are). The ordering dependency and the reference-registry key are
 * therefore both plain strings, matched against UsersFixtures' own
 * constants by convention rather than by import; a rename on either side
 * without updating the other fails loudly via
 * {@see AdministrationFixturesTest}; not by a Deptrac violation, which
 * this design exists to avoid needing at all.
 */
final class AdministrationFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    private const string ADMIN_RANK_NAME = 'System Administrator';

    /**
     * Matches `App\Users\Infrastructure\Fixtures\UsersFixtures::ADMIN_REFERENCE_KEY`.
     */
    private const string USERS_ADMIN_REFERENCE_KEY = 'users.admin_id';

    /**
     * Matches `App\Users\Infrastructure\Fixtures\UsersFixtures::class` —
     * see this class's docblock for why it is a literal string rather
     * than a `::class` reference.
     */
    private const string USERS_FIXTURES_CLASS = 'App\Users\Infrastructure\Fixtures\UsersFixtures';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly FixtureReferenceRegistry $references,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $adminUserId = $this->references->get(self::USERS_ADMIN_REFERENCE_KEY, UserId::class);
        $rankId = $this->references->get(RankLadderFixtures::referenceKey(self::ADMIN_RANK_NAME), RankId::class);

        $envelope = $this->commandBus->dispatch(new GrantAdministrator(
            $adminUserId->value,
            $rankId->value,
            ActorKind::System->value,
        ));

        HandledResult::from($envelope, AdministratorId::class);
    }

    public function getDependencies(): array
    {
        return [self::USERS_FIXTURES_CLASS, RankLadderFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['dev', 'test', 'demo'];
    }
}
