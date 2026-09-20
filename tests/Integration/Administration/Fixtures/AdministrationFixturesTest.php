<?php

declare(strict_types=1);

namespace App\Tests\Integration\Administration\Fixtures;

use App\Administration\Domain\Administrators;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\SignInAccountId;
use App\Administration\Infrastructure\Fixtures\AdministrationFixtures;
use App\Administration\Infrastructure\Fixtures\RankLadderFixtures;
use App\Shared\Infrastructure\Fixtures\FixtureReferenceRegistry;
use App\Tests\Support\Trait\TruncatesFixtureTables;
use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\Username;
use App\Users\Infrastructure\Fixtures\UsersFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Faker\Generator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Drives {@see UsersFixtures}, {@see RankLadderFixtures}, and
 * {@see AdministrationFixtures} in their real dependency order through
 * one shared {@see FixtureReferenceRegistry} — the same wiring
 * `doctrine:fixtures:load` performs — and asserts the seeded admin
 * persona is resolvable as an active administrator afterward.
 */
#[Medium]
#[Group('slow')]
final class AdministrationFixturesTest extends KernelTestCase
{
    use TruncatesFixtureTables;

    #[Test]
    #[TestDox('The seeded admin persona resolves as an active administrator at the System Administrator rank.')]
    public function seeded_admin_persona_is_resolvable_as_an_active_administrator(): void
    {
        $container = static::getContainer();
        $commandBus = $container->get(MessageBusInterface::class);
        $faker = $container->get(Generator::class);
        $em = $container->get(EntityManagerInterface::class);
        $references = $container->get(FixtureReferenceRegistry::class);

        $this->truncateFixtureTables($em->getConnection());

        (new UsersFixtures($commandBus, $faker, $references))->load($em);
        (new RankLadderFixtures($commandBus, $references))->load($em);
        (new AdministrationFixtures($commandBus, $references))->load($em);

        $users = $container->get(Users::class);
        $admin = $users->byUsername(Username::of(UsersFixtures::ADMIN_USERNAME));

        $administrators = $container->get(Administrators::class);
        $administrator = $administrators->forSignInAccount(SignInAccountId::fromString($admin->id()->value));
        self::assertTrue($administrator->isActive());

        $ranks = $container->get(Ranks::class);
        $rank = $ranks->byId($administrator->rankId());
        self::assertTrue($rank->name()->equals(RankName::of('System Administrator')));
    }
}
