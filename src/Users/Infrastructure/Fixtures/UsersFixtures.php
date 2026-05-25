<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Fixtures;

use App\Shared\Infrastructure\Fixtures\FixtureEnv;
use App\Users\Application\Command\RegisterUser;
use App\Users\Domain\ValueObject\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Generator;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Seeds the Users bounded context by dispatching RegisterUser commands.
 *
 * Writes flow through the application layer — no direct aggregate
 * construction, no EntityManager, no repository calls. The fixture is
 * registered against the dev, test, and demo groups; the test group is
 * minimal (curated personas only) by setting FIXTURE_USER_COUNT=0.
 */
final class UsersFixtures extends Fixture implements FixtureGroupInterface
{
    public const ADMIN_USERNAME = 'admin';
    /** @var list<string> */
    public const CURATED_MEMBER_USERNAMES = [
        'member-1',
        'member-2',
        'member-3',
        'member-4',
        'member-5',
    ];

    private const DEFAULT_BULK_COUNT = 25;
    // Caps FIXTURE_USER_COUNT so the Faker unique() pool cannot exhaust
    // and throw OverflowException. 5000 is far above any realistic dev
    // dataset and well inside Faker's userName() entropy budget.
    private const MAX_BULK_COUNT = 5000;
    private const SHARED_PASSWORD = 'fixture-password-1234';
    private const DEFAULT_SEED = 1;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly Generator $faker,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Re-seed at the entry point so the Faker sequence inside this
        // fixture is reproducible even when framework code between
        // fixtures consumes process-wide mt_rand state. Faker::seed()
        // calls mt_srand(), which any random_int / mt_rand caller in
        // the surrounding framework can perturb.
        $this->faker->seed($this->seedValue());

        $this->dispatch(new RegisterUser(self::ADMIN_USERNAME, self::SHARED_PASSWORD, [Role::Admin->value]));

        foreach (self::CURATED_MEMBER_USERNAMES as $username) {
            $this->dispatch(new RegisterUser($username, self::SHARED_PASSWORD, [Role::User->value]));
        }

        $baseSeed = $this->seedValue();
        $bulkCount = $this->bulkCount();
        for ($i = 1; $i <= $bulkCount; $i++) {
            // Re-seed per iteration so Faker output is independent of
            // however much process-wide mt_rand state the surrounding
            // framework (command bus, hasher, UUID generation) consumed
            // since the previous iteration. The counter prefix
            // guarantees uniqueness; the 32-char cap keeps the
            // composite username well under Username::MAX_LENGTH.
            $this->faker->seed($baseSeed + $i);
            $fakerHandle = mb_substr($this->faker->userName(), 0, 32, 'UTF-8');
            $username = sprintf('faker-user-%04d-%s', $i, $fakerHandle);
            $this->dispatch(new RegisterUser($username, self::SHARED_PASSWORD, [Role::User->value]));
        }
    }

    public static function getGroups(): array
    {
        return ['dev', 'test', 'demo'];
    }

    private function dispatch(RegisterUser $command): void
    {
        // RegisterUser is not routed to an async transport, so dispatch()
        // runs the handler inline and any exception surfaces here.
        $this->commandBus->dispatch($command);
    }

    private function bulkCount(): int
    {
        return FixtureEnv::bulkCount('FIXTURE_USER_COUNT', self::DEFAULT_BULK_COUNT, self::MAX_BULK_COUNT);
    }

    private function seedValue(): int
    {
        return FixtureEnv::seed(self::DEFAULT_SEED);
    }
}
