<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Fixtures;

use App\Inventory\Application\Command\LinkItem;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use App\Shared\Infrastructure\Fixtures\FixtureReferenceRegistry;
use App\Shared\Infrastructure\Fixtures\HandledResult;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Seeds 8 item links via {@see LinkItem} command dispatches (LRA-92).
 *
 * Each link exercises a distinct constraint axis so downstream link
 * graph tests have realistic coverage:
 *   1. unlimited link, no reservation, no expiry.
 *   2. reservedQuantity = 10, no expiry.
 *   3. includeUntil in the future (active link).
 *   4. includeUntil in the past (expired link).
 *   5. minRequiredUnits = 2 (must purchase ≥ 2).
 *   6. maxPerPurchaseUnits = 5 (cap per cart).
 *   7. min + max together (2..5 per cart).
 *   8. reserved + unlimited=false + expiry future + min + max.
 *
 * Master/linked item pairs are picked from the 40th-onward items
 * registered by InventoryStockFixtures so the picks do not collide
 * with the items reserved by InventoryCombosFixtures (items 1..14) or
 * InventoryItemGroupsFixtures (items 21..32). Each pair is distinct so
 * the unique (master_id, linked_id) constraint in
 * inventory_item_links holds.
 */
final class InventoryLinkedItemsFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    private const FUTURE_EXPIRY = '2030-01-01T00:00:00+00:00';
    private const PAST_EXPIRY = '2020-01-01T00:00:00+00:00';
    private const COMBINED_EXPIRY = '2030-06-01T00:00:00+00:00';

    /**
     * @var list<array{
     *     reserved: int,
     *     unlimited: bool,
     *     minRequired: int,
     *     maxPerPurchase: int,
     *     includeUntil: ?string,
     * }>
     */
    private const LINKS = [
        // 1. unlimited
        ['reserved' => 0, 'unlimited' => true,  'minRequired' => 0, 'maxPerPurchase' => 0, 'includeUntil' => null],
        // 2. reservedQuantity = 10
        ['reserved' => 10, 'unlimited' => false, 'minRequired' => 0, 'maxPerPurchase' => 0, 'includeUntil' => null],
        // 3. includeUntil future
        ['reserved' => 5, 'unlimited' => false, 'minRequired' => 0, 'maxPerPurchase' => 0,
            'includeUntil' => self::FUTURE_EXPIRY],
        // 4. includeUntil past (expired)
        ['reserved' => 5, 'unlimited' => false, 'minRequired' => 0, 'maxPerPurchase' => 0,
            'includeUntil' => self::PAST_EXPIRY],
        // 5. minRequired = 2
        ['reserved' => 0, 'unlimited' => true,  'minRequired' => 2, 'maxPerPurchase' => 0, 'includeUntil' => null],
        // 6. maxPerPurchase = 5
        ['reserved' => 0, 'unlimited' => true,  'minRequired' => 0, 'maxPerPurchase' => 5, 'includeUntil' => null],
        // 7. min + max
        ['reserved' => 0, 'unlimited' => true,  'minRequired' => 2, 'maxPerPurchase' => 5, 'includeUntil' => null],
        // 8. all axes combined
        ['reserved' => 8, 'unlimited' => false, 'minRequired' => 1, 'maxPerPurchase' => 4,
            'includeUntil' => self::COMBINED_EXPIRY],
    ];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly FixtureReferenceRegistry $references,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Master items start at index 40; each link uses (master, master+1)
        // so the pair is unique and adjacent.
        foreach (self::LINKS as $i => $row) {
            $masterIndex = 40 + ($i * 2);
            $linkedIndex = $masterIndex + 1;

            $masterId = $this->references->get(
                InventoryStockFixtures::referenceKey($masterIndex),
                InventoryItemId::class,
            );
            $linkedId = $this->references->get(
                InventoryStockFixtures::referenceKey($linkedIndex),
                InventoryItemId::class,
            );

            $envelope = $this->commandBus->dispatch(new LinkItem(
                masterItemId: $masterId->value,
                linkedItemId: $linkedId->value,
                reservedQuantityUnits: $row['reserved'],
                unlimited: $row['unlimited'],
                minRequiredUnits: $row['minRequired'],
                maxPerPurchaseUnits: $row['maxPerPurchase'],
                includeUntilIso: $row['includeUntil'],
            ));

            HandledResult::from($envelope, ItemLinkId::class);
        }
    }

    public function getDependencies(): array
    {
        return [InventoryStockFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['inventory-links', 'dev', 'test', 'demo'];
    }
}
