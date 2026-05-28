<?php

declare(strict_types=1);

namespace App\Tests\Integration\Inventory\Fixtures;

use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Static-analysis test that scans every Inventory fixture class on
 * disk and asserts the LRA-92 purity contract:
 *
 *   - No `use` of Doctrine\ORM\EntityManagerInterface.
 *   - No `use` of any Inventory repository port
 *     (App\Inventory\Domain\<Plural>) — fixtures must never read or
 *     write through the repository directly; every mutation goes
 *     through the command bus.
 *   - No `use` of the Catalog RegisterListing command for inventory
 *     items — RegisterInventoryItem is the single canonical entry
 *     point (it dispatches RegisterListing internally). Combo
 *     fixtures legitimately register stand-alone Catalog listings,
 *     so the rule narrows to: forbid RegisterListing only in fixture
 *     classes whose name implies they create Inventory items.
 */
#[Medium]
final class FixtureClassPurityTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/../../../../src/Inventory/Infrastructure/Fixtures';

    private const FORBIDDEN_GENERAL = [
        'Doctrine\\ORM\\EntityManagerInterface',
    ];

    /**
     * Repository ports follow the plural-noun convention
     * (InventoryItems, Vendors, PurchaseOrders, Combos, ItemGroups,
     * ItemLinks). Any `use` of one of these is forbidden in fixture
     * code.
     *
     * @var list<string>
     */
    private const FORBIDDEN_REPOSITORY_PORTS = [
        'App\\Inventory\\Domain\\InventoryItems',
        'App\\Inventory\\Domain\\Vendors',
        'App\\Inventory\\Domain\\PurchaseOrders',
        'App\\Inventory\\Domain\\Combos',
        'App\\Inventory\\Domain\\ItemGroups',
        'App\\Inventory\\Domain\\ItemLinks',
        'App\\Inventory\\Domain\\StockMovementLedger',
        'App\\Inventory\\Domain\\ComboGraphResolver',
    ];

    /**
     * Inventory-item-creating fixtures must use RegisterInventoryItem
     * (LRA-98 cross-bus orchestrator), never the bare
     * RegisterListing — using RegisterListing for an Inventory item
     * would drift the listing kind, ledger account, or fees away from
     * the Inventory aggregate.
     */
    private const FORBIDDEN_LISTING_REGISTRATION_IN_ITEM_FIXTURES
        = 'App\\Catalog\\Application\\Command\\RegisterListing';

    /** @var list<string> */
    private const ITEM_FIXTURE_CLASSES = [
        'InventoryStockFixtures',
    ];

    #[Test]
    #[TestDox('No fixture class uses EntityManagerInterface.')]
    public function no_fixture_uses_entity_manager(): void
    {
        foreach ($this->fixtureFiles() as $file) {
            $contents = $this->read($file);
            foreach (self::FORBIDDEN_GENERAL as $forbidden) {
                self::assertStringNotContainsString(
                    'use ' . $forbidden . ';',
                    $contents,
                    sprintf(
                        'Fixture %s must not import %s — fixtures write only through the command bus.',
                        basename($file),
                        $forbidden,
                    ),
                );
            }
        }
    }

    #[Test]
    #[TestDox('No fixture class imports an Inventory repository port (InventoryItems, Vendors, ...).')]
    public function no_fixture_imports_repository_ports(): void
    {
        foreach ($this->fixtureFiles() as $file) {
            $contents = $this->read($file);
            foreach (self::FORBIDDEN_REPOSITORY_PORTS as $port) {
                self::assertStringNotContainsString(
                    'use ' . $port . ';',
                    $contents,
                    sprintf(
                        'Fixture %s must not import the %s repository port — '
                        . 'all writes go through the command bus.',
                        basename($file),
                        $port,
                    ),
                );
            }
        }
    }

    #[Test]
    #[TestDox(
        'Inventory item fixtures use RegisterInventoryItem (LRA-98 cross-bus) — never bare RegisterListing.',
    )]
    public function item_fixtures_do_not_dispatch_register_listing_directly(): void
    {
        foreach (self::ITEM_FIXTURE_CLASSES as $className) {
            $file = self::FIXTURES_DIR . '/' . $className . '.php';
            self::assertFileExists($file, sprintf('Expected fixture %s to exist.', $className));
            $contents = $this->read($file);
            self::assertStringNotContainsString(
                'use ' . self::FORBIDDEN_LISTING_REGISTRATION_IN_ITEM_FIXTURES . ';',
                $contents,
                sprintf(
                    '%s registers Inventory items; it must dispatch RegisterInventoryItem '
                    . '(the LRA-98 cross-bus orchestrator), never RegisterListing directly.',
                    $className,
                ),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function fixtureFiles(): array
    {
        $glob = glob(self::FIXTURES_DIR . '/*.php');
        if ($glob === false) {
            self::fail('Could not read the inventory fixtures directory.');
        }

        sort($glob, SORT_STRING);

        return $glob;
    }

    private function read(string $file): string
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            self::fail(sprintf('Could not read fixture file %s.', $file));
        }

        return $contents;
    }
}
