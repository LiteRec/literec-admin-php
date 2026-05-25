<?php

declare(strict_types=1);

namespace App\Tests\Integration\Users\Fixtures;

use App\Tests\Support\Trait\TruncatesFixtureTables;
use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\Role;
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

#[Medium]
#[Group('slow')]
final class UsersFixturesTest extends KernelTestCase
{
    use TruncatesFixtureTables;

    #[Test]
    #[TestDox(
        'Loads admin + curated members + a small bulk batch via the command bus '
        . 'and exposes them through the Users repository port.',
    )]
    public function loads_curated_personas_and_bulk_users_through_the_command_bus(): void
    {
        $_ENV['FIXTURE_USER_COUNT'] = '3';
        try {
            $container = static::getContainer();

            $commandBus = $container->get(MessageBusInterface::class);
            $faker = $container->get(Generator::class);
            $em = $container->get(EntityManagerInterface::class);

            // Truncate so the fixture's curated personas can re-register
            // without colliding with rows from a prior fixture load
            // (e.g. composer db:reset-test run before the suite).
            $this->truncateFixtureTables($em->getConnection());

            $fixture = new UsersFixtures($commandBus, $faker);
            $fixture->load($em);

            $users = $container->get(Users::class);

            $admin = $users->byUsername(Username::of(UsersFixtures::ADMIN_USERNAME));
            self::assertContains(Role::Admin, $admin->roles(), 'Admin persona must carry ROLE_ADMIN.');

            foreach (UsersFixtures::CURATED_MEMBER_USERNAMES as $username) {
                self::assertTrue(
                    $users->existsWithUsername(Username::of($username)),
                    sprintf('Curated member %s should be present after fixture load.', $username),
                );
            }

            // Verify the bulk loop ran: first faker user follows the
            // `faker-user-0001-` prefix convention.
            $rows = $em->getConnection()
                ->executeQuery("SELECT username FROM \"user\" WHERE username LIKE 'faker-user-%' ORDER BY username")
                ->fetchFirstColumn();
            self::assertCount(3, $rows, 'FIXTURE_USER_COUNT=3 should produce three bulk users.');
        } finally {
            unset($_ENV['FIXTURE_USER_COUNT']);
        }
    }
}
