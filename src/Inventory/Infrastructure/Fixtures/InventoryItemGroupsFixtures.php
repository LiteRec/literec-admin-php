<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Fixtures;

use App\Inventory\Application\Command\AddItemToGroup;
use App\Inventory\Application\Command\CreateItemGroup;
use App\Inventory\Application\Command\RecolorItemGroup;
use App\Inventory\Application\Command\RenameItemGroup;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Shared\Infrastructure\Fixtures\FixtureReferenceRegistry;
use App\Shared\Infrastructure\Fixtures\HandledResult;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Seeds 6 item groups via the command bus (LRA-92):
 *   - 3 "All Facilities" groups (empty facilityCodes).
 *   - 3 facility-scoped groups (FAC-A only, FAC-B only, both).
 *
 * 2 groups are renamed and 1 is recolored to exercise the
 * {@see RenameItemGroup} and {@see RecolorItemGroup} commands; every
 * group gets 2 inventory items added via {@see AddItemToGroup} so the
 * inventory_item_group_members table is non-empty.
 *
 * All writes flow through the command bus — no direct aggregate
 * construction, no EntityManager.
 */
final class InventoryItemGroupsFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    /**
     * @var list<array{name: string, colorHex: string, facilities: list<string>, rename: ?string, recolor: ?string}>
     */
    private const GROUPS = [
        [
            'name' => 'Top Sellers',
            'colorHex' => '#FF0000',
            'facilities' => [],
            'rename' => 'Top Sellers Q1',
            'recolor' => null,
        ],
        [
            'name' => 'Seasonal Promos',
            'colorHex' => '#00AA00',
            'facilities' => [],
            'rename' => null,
            'recolor' => '#00CC00',
        ],
        [
            'name' => 'Member Favorites',
            'colorHex' => '#0044FF',
            'facilities' => [],
            'rename' => null,
            'recolor' => null,
        ],
        [
            'name' => 'FAC-A Exclusives',
            'colorHex' => '#AA5500',
            'facilities' => [InventoryBaseFixtures::FACILITY_PRIMARY],
            'rename' => null,
            'recolor' => null,
        ],
        [
            'name' => 'FAC-B Exclusives',
            'colorHex' => '#5500AA',
            'facilities' => [InventoryBaseFixtures::FACILITY_SECONDARY],
            'rename' => 'FAC-B Specials',
            'recolor' => null,
        ],
        [
            'name' => 'Multi-Facility Bundle',
            'colorHex' => '#222222',
            'facilities' => [
                InventoryBaseFixtures::FACILITY_PRIMARY,
                InventoryBaseFixtures::FACILITY_SECONDARY,
            ],
            'rename' => null,
            'recolor' => null,
        ],
    ];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly FixtureReferenceRegistry $references,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Combos consume items 1..14; start at 20 to leave a 6-item
        // buffer in case a future fixture adds another combo. Item
        // groups consume 21..32 (6 groups × 2 members) — well below
        // the 40 floor reserved for Links.
        $globalItemIndex = 20;

        foreach (self::GROUPS as $row) {
            $groupId = $this->createGroup($row['name'], $row['colorHex'], $row['facilities']);

            if ($row['rename'] !== null) {
                $this->commandBus->dispatch(new RenameItemGroup($groupId->value, $row['rename']));
            }
            if ($row['recolor'] !== null) {
                $this->commandBus->dispatch(new RecolorItemGroup($groupId->value, $row['recolor']));
            }

            // 2 items per group.
            for ($m = 0; $m < 2; $m++) {
                $globalItemIndex++;
                $itemId = $this->references->get(
                    InventoryStockFixtures::referenceKey($globalItemIndex),
                    InventoryItemId::class,
                );
                $this->commandBus->dispatch(new AddItemToGroup(
                    itemGroupId: $groupId->value,
                    inventoryItemId: $itemId->value,
                ));
            }
        }
    }

    public function getDependencies(): array
    {
        return [InventoryStockFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['inventory-groups', 'dev', 'test', 'demo'];
    }

    /**
     * @param list<string> $facilityCodes
     */
    private function createGroup(string $name, string $colorHex, array $facilityCodes): ItemGroupId
    {
        $envelope = $this->commandBus->dispatch(new CreateItemGroup(
            name: $name,
            colorHex: $colorHex,
            facilityCodes: $facilityCodes,
        ));

        return HandledResult::from($envelope, ItemGroupId::class);
    }
}
