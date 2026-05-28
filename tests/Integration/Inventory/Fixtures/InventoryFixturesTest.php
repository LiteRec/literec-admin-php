<?php

declare(strict_types=1);

namespace App\Tests\Integration\Inventory\Fixtures;

use App\Catalog\Infrastructure\Fixtures\CatalogBaseFixtures;
use App\Inventory\Infrastructure\Fixtures\InventoryBaseFixtures;
use App\Inventory\Infrastructure\Fixtures\InventoryCombosFixtures;
use App\Inventory\Infrastructure\Fixtures\InventoryItemGroupsFixtures;
use App\Inventory\Infrastructure\Fixtures\InventoryLinkedItemsFixtures;
use App\Inventory\Infrastructure\Fixtures\InventoryPurchaseOrdersFixtures;
use App\Inventory\Infrastructure\Fixtures\InventoryStockFixtures;
use App\Inventory\Infrastructure\Fixtures\InventoryVendorsFixtures;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Smoke-tests the full LRA-92 fixture set: loading every Inventory +
 * Catalog fixture class through the container-resolved fixtures
 * loader populates every expected table.
 *
 * Repeated-load determinism is not covered here — production wiring
 * uses the real wall clock + UUID v7 generator, so successive loads
 * produce identical *content* but distinct identifiers/timestamps.
 * The dev-env override (FixedClock + SeededIdentityGenerator) makes
 * a `composer db:reset` deterministic; a follow-up ticket should
 * exercise that path in a dedicated test once the test-container
 * binding is added.
 */
#[Medium]
#[Group('slow')]
#[BackupGlobals(true)]
final class InventoryFixturesTest extends KernelTestCase
{
    /** @var list<string> */
    private const TABLES_TO_TRUNCATE = [
        'inventory_item_group_members',
        'inventory_item_groups',
        'inventory_item_links',
        'inventory_combo_components',
        'inventory_combos',
        'inventory_stock_movements',
        'inventory_stock_batches',
        'inventory_purchase_order_lines',
        'inventory_purchase_orders',
        'inventory_items',
        'inventory_vendors',
        'catalog_listings',
    ];

    #[Test]
    #[TestDox('Loading every LRA-92 fixture through the container populates every expected table.')]
    public function it_loads_full_inventory_dataset_through_command_bus_only(): void
    {
        // Constrain the bulk count so the test stays in the slow group
        // budget while still exercising every code path. #[BackupGlobals]
        // on the class restores $_ENV / $_SERVER for us on teardown.
        $_ENV['FIXTURE_INVENTORY_ITEM_COUNT'] = '92';
        $_SERVER['FIXTURE_INVENTORY_ITEM_COUNT'] = '92';

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $connection = $em->getConnection();

        $this->truncate($connection);

        $loader = new SymfonyFixturesLoader();
        foreach ($this->fixtureClasses() as $class) {
            $instance = $container->get($class);
            self::assertInstanceOf(FixtureInterface::class, $instance, sprintf(
                'Container service %s must implement FixtureInterface.',
                $class,
            ));
            $loader->addFixture($instance);
        }

        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($loader->getFixtures(), true);

        $this->assertRowCount($connection, 'catalog_listings', 7, '>=');
        $this->assertRowCount($connection, 'inventory_vendors', 5, '=');
        $this->assertRowCount($connection, 'inventory_items', 92, '=');
        $this->assertRowCount($connection, 'inventory_stock_batches', 1, '>=');
        $this->assertRowCount($connection, 'inventory_purchase_orders', 12, '=');
        $this->assertRowCount($connection, 'inventory_purchase_order_lines', 36, '=');
        $this->assertRowCount($connection, 'inventory_combos', 4, '=');
        $this->assertRowCount($connection, 'inventory_combo_components', 14, '=');
        $this->assertRowCount($connection, 'inventory_item_groups', 6, '=');
        $this->assertRowCount($connection, 'inventory_item_group_members', 12, '=');
        $this->assertRowCount($connection, 'inventory_item_links', 8, '=');
    }

    /**
     * @return list<class-string>
     */
    private function fixtureClasses(): array
    {
        return [
            CatalogBaseFixtures::class,
            InventoryBaseFixtures::class,
            InventoryVendorsFixtures::class,
            InventoryStockFixtures::class,
            InventoryPurchaseOrdersFixtures::class,
            InventoryCombosFixtures::class,
            InventoryItemGroupsFixtures::class,
            InventoryLinkedItemsFixtures::class,
        ];
    }

    private function truncate(Connection $connection): void
    {
        $platform = $connection->getDatabasePlatform();
        $quotedTables = array_map(
            // quoteSingleIdentifier is the DBAL 4+ replacement for the
            // deprecated quoteIdentifier; every entry in
            // TABLES_TO_TRUNCATE is an unqualified single name.
            static fn (string $table): string => $platform->quoteSingleIdentifier($table),
            self::TABLES_TO_TRUNCATE,
        );
        $connection->executeStatement(
            sprintf(
                'TRUNCATE %s RESTART IDENTITY CASCADE',
                implode(', ', $quotedTables),
            ),
        );
    }

    private function assertRowCount(
        Connection $connection,
        string $table,
        int $expected,
        string $operator,
    ): void {
        $value = $connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
        $actual = is_numeric($value) ? (int) $value : 0;

        match ($operator) {
            '=' => self::assertSame(
                $expected,
                $actual,
                sprintf('Expected %d rows in %s; got %d.', $expected, $table, $actual),
            ),
            '>=' => self::assertGreaterThanOrEqual(
                $expected,
                $actual,
                sprintf('Expected ≥%d rows in %s; got %d.', $expected, $table, $actual),
            ),
            default => self::fail(sprintf('Unsupported operator: %s', $operator)),
        };
    }
}
