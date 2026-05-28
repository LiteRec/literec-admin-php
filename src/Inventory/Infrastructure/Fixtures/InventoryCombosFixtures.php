<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Fixtures;

use App\Catalog\Application\Command\RegisterListing;
use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Application\Command\DefineCombo;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Shared\Infrastructure\Fixtures\FixtureReferenceRegistry;
use App\Shared\Infrastructure\Fixtures\HandledResult;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Seeds 4 combos via the command bus (LRA-92):
 *   1. A simple 2-component combo.
 *   2. A 3-component combo.
 *   3. A 4-component combo.
 *   4. A 5-component combo.
 *
 * Each combo wraps a freshly minted Catalog Listing (registered via
 * {@see RegisterListing}); the {@see DefineCombo} command then attaches
 * the component items to that listing. Both commands flow through the
 * command bus — no direct aggregate construction.
 */
final class InventoryCombosFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    /**
     * Per-combo (componentCount, name, code) shape. Component counts
     * range from 2..5 (totalling 2+3+4+5 = 14 component slots);
     * component item references are picked from the first 14 items
     * registered by InventoryStockFixtures so the fixture is stable
     * regardless of FIXTURE_INVENTORY_ITEM_COUNT.
     *
     * @var list<array{name: string, code: string, components: int}>
     */
    private const COMBOS = [
        ['name' => 'Combo Pack A', 'code' => 'COMBO-A', 'components' => 2],
        ['name' => 'Combo Pack B', 'code' => 'COMBO-B', 'components' => 3],
        ['name' => 'Combo Pack C', 'code' => 'COMBO-C', 'components' => 4],
        ['name' => 'Combo Pack D', 'code' => 'COMBO-D', 'components' => 5],
    ];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly FixtureReferenceRegistry $references,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $globalItemIndex = 0;
        foreach (self::COMBOS as $row) {
            $listingId = $this->registerComboListing($row['code'], $row['name']);

            $components = [];
            for ($c = 0; $c < $row['components']; $c++) {
                $globalItemIndex++;
                $itemId = $this->references->get(
                    InventoryStockFixtures::referenceKey($globalItemIndex),
                    InventoryItemId::class,
                );
                $components[] = [
                    'itemId' => $itemId->value,
                    'quantityPerCombo' => 1 + ($c % 2),
                ];
            }

            $envelope = $this->commandBus->dispatch(new DefineCombo(
                listingId: $listingId->value,
                components: $components,
            ));

            HandledResult::from($envelope, ComboId::class);
        }
    }

    public function getDependencies(): array
    {
        return [InventoryStockFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['inventory-combos', 'dev', 'test', 'demo'];
    }

    private function registerComboListing(string $code, string $name): ListingId
    {
        $envelope = $this->commandBus->dispatch(new RegisterListing(
            code: $code,
            kind: ListingKind::Inventory->value,
            name: $name,
            fees: [[
                'amountCents' => 1_500,
                'currency' => 'USD',
                'label' => 'Combo price',
            ]],
            taxApply: true,
            taxIncludedInFee: false,
            ledgerAccount: '5000',
        ));

        return HandledResult::from($envelope, ListingId::class);
    }
}
