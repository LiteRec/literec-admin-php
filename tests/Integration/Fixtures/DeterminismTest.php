<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fixtures;

use App\Households\Infrastructure\Fixtures\HouseholdsFixtures;
use App\Tests\Support\Trait\TruncatesFixtureTables;
use App\Users\Infrastructure\Fixtures\UsersFixtures;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Locks in the determinism contract for the fixture loaders: the Faker
 * seed (FIXTURE_SEED, default 1) plus the configured bulk counts must
 * produce byte-identical content across runs for every stable column.
 *
 * Identity columns (UUID v7) are intentionally excluded because v7
 * embeds the wall-clock timestamp — that variance is documented in the
 * fixtures README and is not a determinism violation for the content
 * the system relies on for tests and demos.
 */
#[Medium]
#[Group('slow')]
final class DeterminismTest extends KernelTestCase
{
    use TruncatesFixtureTables;

    private const SEED = 1;
    private const USER_COUNT = '3';
    private const HOUSEHOLD_COUNT = '2';

    #[Test]
    #[TestDox(
        'Re-running the fixtures with the same FIXTURE_SEED produces identical content '
        . 'for every stable column across both Users and Households.',
    )]
    public function repeated_loads_produce_identical_content_for_stable_columns(): void
    {
        $previousUserCount = $this->captureEnv('FIXTURE_USER_COUNT');
        $previousHouseholdCount = $this->captureEnv('FIXTURE_HOUSEHOLD_COUNT');
        $previousSeed = $this->captureEnv('FIXTURE_SEED');
        $_ENV['FIXTURE_USER_COUNT'] = self::USER_COUNT;
        $_ENV['FIXTURE_HOUSEHOLD_COUNT'] = self::HOUSEHOLD_COUNT;
        $_ENV['FIXTURE_SEED'] = (string) self::SEED;
        $_SERVER['FIXTURE_USER_COUNT'] = self::USER_COUNT;
        $_SERVER['FIXTURE_HOUSEHOLD_COUNT'] = self::HOUSEHOLD_COUNT;
        $_SERVER['FIXTURE_SEED'] = (string) self::SEED;

        try {
            // Pre-truncate so the first snapshot is not contaminated
            // by data left over from earlier tests in the same suite.
            $this->truncateAll();
            $first = $this->loadAndSnapshot();
            $this->truncateAll();
            $second = $this->loadAndSnapshot();

            self::assertSame(
                $first['users'],
                $second['users'],
                'Users content must match byte-for-byte across runs at the same seed.',
            );
            self::assertSame(
                $first['households'],
                $second['households'],
                'Households content must match byte-for-byte across runs at the same seed.',
            );
            self::assertSame(
                $first['members'],
                $second['members'],
                'Household members content must match byte-for-byte across runs at the same seed.',
            );
        } finally {
            $this->restoreEnv('FIXTURE_USER_COUNT', $previousUserCount);
            $this->restoreEnv('FIXTURE_HOUSEHOLD_COUNT', $previousHouseholdCount);
            $this->restoreEnv('FIXTURE_SEED', $previousSeed);
        }
    }

    /**
     * @return array{
     *     users: list<array<string, mixed>>,
     *     households: list<array<string, mixed>>,
     *     members: list<array<string, mixed>>,
     * }
     */
    private function loadAndSnapshot(): array
    {
        $container = static::getContainer();
        $commandBus = $container->get(MessageBusInterface::class);
        $em = $container->get(EntityManagerInterface::class);

        // Fresh Generator per snapshot; both fixtures re-seed it from
        // FIXTURE_SEED inside their own load(), so we do not seed here.
        $faker = Factory::create('en_US');

        (new UsersFixtures($commandBus, $faker))->load($em);
        (new HouseholdsFixtures($commandBus, $faker))->load($em);

        $connection = $em->getConnection();

        return [
            'users' => $this->fetchRows(
                $connection,
                'SELECT username, roles, is_active FROM "user" ORDER BY username',
            ),
            'households' => $this->fetchRows(
                $connection,
                'SELECT name, street, unit, city, state, postal_code, country '
                . 'FROM households ORDER BY name',
            ),
            'members' => $this->fetchRows(
                $connection,
                'SELECT first_name, last_name, date_of_birth, gender, '
                . 'residency_status, is_primary, is_active '
                . 'FROM household_members ORDER BY last_name, first_name, date_of_birth',
            ),
        ];
    }

    private function truncateAll(): void
    {
        $this->truncateFixtureTables(
            static::getContainer()->get(EntityManagerInterface::class)->getConnection(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRows(Connection $connection, string $sql): array
    {
        return $connection->fetchAllAssociative($sql);
    }

    /**
     * @return array{env: ?string, server: ?string}
     */
    private function captureEnv(string $key): array
    {
        return [
            'env' => $this->captureFrom($_ENV, $key),
            'server' => $this->captureFrom($_SERVER, $key),
        ];
    }

    /**
     * @param array<array-key, mixed> $source
     */
    private function captureFrom(array $source, string $key): ?string
    {
        if (!array_key_exists($key, $source)) {
            return null;
        }
        $value = $source[$key];

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param array{env: ?string, server: ?string} $previous
     */
    private function restoreEnv(string $key, array $previous): void
    {
        if ($previous['env'] === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $previous['env'];
        }
        if ($previous['server'] === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $previous['server'];
        }
    }
}
