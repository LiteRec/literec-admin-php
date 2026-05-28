<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Fixtures;

use App\Inventory\Application\Command\AdjustStock;
use App\Inventory\Application\Command\ReceiveStockManually;
use App\Inventory\Application\Command\RegisterInventoryItem;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\StockAdjustmentReason;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Shared\Infrastructure\Fixtures\FixtureEnv;
use App\Shared\Infrastructure\Fixtures\FixtureReferenceRegistry;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Generator;
use LogicException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Seeds Inventory items and their initial stock state via the cross-bus
 * {@see RegisterInventoryItem} orchestrator (LRA-98) followed by
 * {@see ReceiveStockManually} and {@see AdjustStock} dispatches.
 *
 * Every write flows through the command bus — no direct aggregate
 * construction, no EntityManager, no repository calls. The fixture
 * exercises:
 *   - 80 InventoryItems by default (FIXTURE_INVENTORY_ITEM_COUNT to
 *     override; capped at 500 so the bulk load stays under a minute).
 *   - Per-item manual receipt across the two canonical facilities so
 *     downstream FIFO read paths see realistic batch data.
 *   - Variance adjustments on a small slice of items so the
 *     inventory_stock_movements ledger has ADJUSTED rows alongside the
 *     RECEIVED rows from manual receipt.
 *
 * Bulk records reference the curated vendor codes from
 * {@see InventoryVendorsFixtures} via the fixture reference registry —
 * no direct repository read.
 */
final class InventoryStockFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public const REFERENCE_PREFIX = 'inventory.item.';

    private const DEFAULT_BULK_COUNT = 92;
    private const MAX_BULK_COUNT = 500;
    // Downstream fixtures reference items by index in reserved ranges:
    //   Combos       1..14
    //   Item groups  21..32
    //   Links        40..55
    //   PO lines     56..91  (12 POs × 3 lines, offset by 55)
    // The bulk count cannot fall below 91 without breaking the
    // downstream chain — round up to 92 so the floor matches the
    // default count.
    private const MIN_BULK_COUNT = 92;
    private const DEFAULT_SEED = 1;
    // Offset relative to FIXTURE_SEED so this fixture's Faker sequence
    // does not collide with Users/Households fixtures running before it.
    private const SEED_OFFSET = 30_000;

    /**
     * Variance reason cycle for the adjust-stock slice. Cycled so every
     * StockAdjustmentReason case sees coverage in a fully-loaded
     * fixtures dataset.
     *
     * @var list<StockAdjustmentReason>
     */
    private const ADJUSTMENT_REASONS = [
        StockAdjustmentReason::DAMAGED,
        StockAdjustmentReason::FOUND,
        StockAdjustmentReason::MISCOUNT,
        StockAdjustmentReason::THEFT,
        StockAdjustmentReason::OTHER,
    ];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly Generator $faker,
        private readonly FixtureReferenceRegistry $references,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $baseSeed = $this->seedValue();
        $this->faker->seed($baseSeed + self::SEED_OFFSET);

        $count = $this->bulkCount();
        $vendorCodes = InventoryVendorsFixtures::vendorCodes();

        for ($i = 1; $i <= $count; $i++) {
            // Per-iteration re-seed so framework callouts between
            // dispatches cannot perturb Faker's sequence.
            $this->faker->seed($baseSeed + self::SEED_OFFSET + $i);

            $itemId = $this->registerItem($i, $vendorCodes);
            $this->references->set(self::referenceKey($i), $itemId);

            $this->receiveInitialStock($itemId->value, $i);

            // Adjust every 7th item so the inventory_stock_movements
            // ledger gets a representative ADJUSTED slice without
            // adjusting the whole dataset.
            if ($i % 7 === 0) {
                $this->adjustStock($itemId->value, $i);
            }
        }
    }

    public function getDependencies(): array
    {
        return [InventoryVendorsFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['inventory-stock', 'dev', 'test', 'demo'];
    }

    public static function referenceKey(int $index): string
    {
        return self::REFERENCE_PREFIX . $index;
    }

    /**
     * @param list<string> $vendorCodes
     */
    private function registerItem(int $index, array $vendorCodes): InventoryItemId
    {
        // Use the curated vendor list cyclically so every vendor in
        // InventoryVendorsFixtures is referenced by at least one item.
        $vendorIndex = ($index - 1) % count($vendorCodes);
        $vendorRef = InventoryVendorsFixtures::referenceKey($vendorCodes[$vendorIndex]);
        $vendorId = $this->references->get($vendorRef, VendorId::class);

        // 4-digit zero-padded suffix keeps codes well below the
        // ListingCode max length and sortable by index.
        $code = sprintf('ITEM-%04d', $index);
        $name = sprintf('Test Item %04d', $index);

        $envelope = $this->commandBus->dispatch(new RegisterInventoryItem(
            code: $code,
            name: $name,
            kind: 'INVENTORY',
            fees: [[
                'amountCents' => 100 + ($index * 25),
                'currency' => 'USD',
                'label' => 'Sale price',
            ]],
            // tax_included_in_fee can only be true when tax_apply is
            // also true (Catalog TaxTreatment invariant). Cycle both
            // flags together to satisfy that constraint while keeping
            // the dataset diverse: 1/3 of items skip tax entirely;
            // among taxed items, 1/5 mark the fee as tax-inclusive.
            taxApply: ($index % 3) !== 0,
            taxIncludedInFee: ($index % 3) !== 0 && ($index % 5) === 0,
            ledgerAccount: '5000',
            primaryVendorId: $vendorId->value,
            posColorHex: self::cyclePosColor($index),
            trackInventory: true,
            rentable: ($index % 11) === 0,
            reorderThresholdUnits: 5 + ($index % 10),
        ));

        // RegisterInventoryItemHandler returns a raw string (the new
        // item's UUID), not an InventoryItemId value object — see
        // RegisterInventoryItemHandler::__invoke(): string. That's why
        // we cannot use the typed HandledResult::from() helper used by
        // every other fixture in this set; we extract the string by
        // hand and wrap it in the value object here.
        $stamp = $envelope->last(HandledStamp::class);
        if (!$stamp instanceof HandledStamp) {
            throw new LogicException(
                'RegisterInventoryItem handler did not produce a HandledStamp; cannot continue stock fixture load.',
            );
        }
        $result = $stamp->getResult();
        if (!is_string($result)) {
            throw new LogicException(sprintf(
                'RegisterInventoryItem handler returned %s, expected string item id.',
                get_debug_type($result),
            ));
        }

        return InventoryItemId::fromString($result);
    }

    private function receiveInitialStock(string $itemId, int $index): void
    {
        // Spread initial receipts across the two facilities so the
        // per-facility FIFO read paths exercised by LRA-97 have data
        // on both sides. Even indices land on FAC-A; odd indices land
        // on FAC-B with a second smaller receipt on FAC-A so the
        // bilateral case is also represented.
        $primaryFacility = ($index % 2) === 0
            ? InventoryBaseFixtures::FACILITY_PRIMARY
            : InventoryBaseFixtures::FACILITY_SECONDARY;

        $this->commandBus->dispatch(new ReceiveStockManually(
            itemId: $itemId,
            facilityCode: $primaryFacility,
            quantityUnits: 20 + ($index % 15),
            costPerUnitCents: 250 + ($index * 5),
            comment: sprintf('Fixture initial receipt for item %04d', $index),
            purchaseOrderLineId: null,
        ));

        if (($index % 2) === 1) {
            $this->commandBus->dispatch(new ReceiveStockManually(
                itemId: $itemId,
                facilityCode: InventoryBaseFixtures::FACILITY_PRIMARY,
                quantityUnits: 5 + ($index % 5),
                costPerUnitCents: 240 + ($index * 5),
                comment: sprintf('Fixture top-up receipt for item %04d', $index),
                purchaseOrderLineId: null,
            ));
        }
    }

    private function adjustStock(string $itemId, int $index): void
    {
        $cycleIndex = intdiv($index, 7);
        $reason = self::ADJUSTMENT_REASONS[($cycleIndex - 1) % count(self::ADJUSTMENT_REASONS)];
        $delta = ($cycleIndex % 2 === 0) ? -2 : 3;
        $baseQty = 20 + ($index % 15);

        $this->commandBus->dispatch(new AdjustStock(
            itemId: $itemId,
            facilityCode: InventoryBaseFixtures::FACILITY_PRIMARY,
            targetQuantityUnits: max(0, $baseQty + $delta),
            reason: sprintf('Fixture variance for item %04d', $index),
            adjustmentSubReason: $reason->value,
        ));
    }

    private static function cyclePosColor(int $index): string
    {
        $palette = ['#FF8800', '#0088FF', '#88AA00', '#AA00CC', '#00CCAA', '#CC4444'];

        return $palette[$index % count($palette)];
    }

    private function bulkCount(): int
    {
        $raw = FixtureEnv::bulkCount(
            'FIXTURE_INVENTORY_ITEM_COUNT',
            self::DEFAULT_BULK_COUNT,
            self::MAX_BULK_COUNT,
        );

        return max(self::MIN_BULK_COUNT, $raw);
    }

    private function seedValue(): int
    {
        return FixtureEnv::seed(self::DEFAULT_SEED);
    }
}
