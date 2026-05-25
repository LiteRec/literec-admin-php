<?php

declare(strict_types=1);

namespace App\Tests\Integration\Households\Fixtures;

use App\Households\Infrastructure\Fixtures\HouseholdsFixtures;
use App\Tests\Support\Trait\TruncatesFixtureTables;
use Doctrine\DBAL\Connection;
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
final class HouseholdsFixturesTest extends KernelTestCase
{
    use TruncatesFixtureTables;

    #[Test]
    #[TestDox(
        'Loads four curated households + a small bulk batch via the command bus, '
        . 'including a residency change and a deactivation transition.',
    )]
    public function loads_curated_households_and_bulk_batch_through_the_command_bus(): void
    {
        $previousEnv = $_ENV['FIXTURE_HOUSEHOLD_COUNT'] ?? null;
        $previousServer = $_SERVER['FIXTURE_HOUSEHOLD_COUNT'] ?? null;
        $previousProcess = getenv('FIXTURE_HOUSEHOLD_COUNT');

        $_ENV['FIXTURE_HOUSEHOLD_COUNT'] = '2';
        $_SERVER['FIXTURE_HOUSEHOLD_COUNT'] = '2';
        putenv('FIXTURE_HOUSEHOLD_COUNT=2');
        try {
            $container = static::getContainer();

            $commandBus = $container->get(MessageBusInterface::class);
            $faker = $container->get(Generator::class);
            $em = $container->get(EntityManagerInterface::class);
            $connection = $em->getConnection();

            // Truncate so the fixture starts from a clean slate when
            // an earlier composer db:reset-test or sibling slow test
            // has already populated the database.
            $this->truncateFixtureTables($connection);

            $fixture = new HouseholdsFixtures($commandBus, $faker);
            $fixture->load($em);

            self::assertSame(
                6,
                $this->countOne($connection, 'SELECT COUNT(*) FROM households'),
                '4 curated + 2 bulk households expected.',
            );

            self::assertSame(
                2,
                $this->countOne(
                    $connection,
                    "SELECT COUNT(*) FROM households WHERE name LIKE '% Household 000_'",
                ),
                'Two bulk households should match the "<Surname> Household NNNN" naming convention.',
            );

            $curated = $connection->fetchFirstColumn(
                "SELECT name FROM households "
                . "WHERE name IN ('Smith Single', 'Jones Family', 'Miller Senior', 'Brown Inactive') "
                . 'ORDER BY name'
            );
            self::assertSame(
                ['Brown Inactive', 'Jones Family', 'Miller Senior', 'Smith Single'],
                $curated,
                'All four curated households should be present.',
            );

            self::assertSame(
                4,
                $this->countOne(
                    $connection,
                    'SELECT COUNT(*) FROM household_members hm '
                    . 'JOIN households h ON h.id = hm.household_id '
                    . "WHERE h.name = 'Jones Family'",
                ),
                'Jones Family should have 4 members.',
            );

            self::assertGreaterThanOrEqual(
                1,
                $this->countOne(
                    $connection,
                    'SELECT COUNT(*) FROM household_residency_history hrh '
                    . 'JOIN households h ON h.id = hrh.household_id '
                    . "WHERE h.name = 'Miller Senior'",
                ),
                'Miller Senior household must record at least one residency-history entry.',
            );

            self::assertGreaterThanOrEqual(
                1,
                $this->countOne(
                    $connection,
                    'SELECT COUNT(*) FROM household_members hm '
                    . 'JOIN households h ON h.id = hm.household_id '
                    . "WHERE h.name = 'Brown Inactive' "
                    . 'AND hm.is_active = false '
                    . 'AND hm.deactivated_at IS NOT NULL',
                ),
                'Brown Inactive should include at least one deactivated member.',
            );
        } finally {
            if ($previousEnv === null) {
                unset($_ENV['FIXTURE_HOUSEHOLD_COUNT']);
            } else {
                $_ENV['FIXTURE_HOUSEHOLD_COUNT'] = $previousEnv;
            }
            if ($previousServer === null) {
                unset($_SERVER['FIXTURE_HOUSEHOLD_COUNT']);
            } else {
                $_SERVER['FIXTURE_HOUSEHOLD_COUNT'] = $previousServer;
            }
            if ($previousProcess === false) {
                putenv('FIXTURE_HOUSEHOLD_COUNT');
            } else {
                putenv('FIXTURE_HOUSEHOLD_COUNT=' . $previousProcess);
            }
        }
    }

    private function countOne(Connection $connection, string $sql): int
    {
        $value = $connection->fetchOne($sql);
        if (!is_numeric($value)) {
            self::fail(sprintf('Expected numeric COUNT(*) result, got %s.', get_debug_type($value)));
        }

        return (int) $value;
    }
}
