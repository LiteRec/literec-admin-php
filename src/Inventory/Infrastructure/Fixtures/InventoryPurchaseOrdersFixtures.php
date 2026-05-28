<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Fixtures;

use App\Inventory\Application\Command\CreatePurchaseOrder;
use App\Inventory\Application\Command\MarkPurchaseOrderSent;
use App\Inventory\Application\Command\ReceivePurchaseOrderLine;
use App\Inventory\Application\Command\VerifyDelivery;
use App\Inventory\Application\Query\GetPurchaseOrderDetail;
use App\Inventory\Application\Query\View\PurchaseOrderDetailView;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Shared\Infrastructure\Fixtures\FixedClock;
use App\Shared\Infrastructure\Fixtures\FixtureReferenceRegistry;
use App\Shared\Infrastructure\Fixtures\HandledResult;
use DateInterval;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Seeds 12 purchase orders via the command bus (LRA-92):
 *   - 6 PurchaseOrders left in `Draft` for the LRA-90 list page to
 *     render the "no actions yet" lifecycle.
 *   - 6 PurchaseOrders advanced through Send → Receive partial →
 *     Receive full → Verify so the LRA-90 detail page has data for
 *     every transition state.
 *
 * Every transition runs through its own command-bus dispatch. Line
 * identities are fetched via the query bus
 * ({@see GetPurchaseOrderDetail}) so this fixture never imports a
 * domain repository port. The injected {@see ClockInterface} is the
 * fixture-only {@see FixedClock} in dev/test env; the fixture
 * `advance()`s it between phases so the recorded send/receive/verify
 * timestamps reflect a plausible 3-day lifecycle.
 *
 * Verification needs a User id; the curated admin from
 * {@see \App\Users\Infrastructure\Fixtures\UsersFixtures::ADMIN_USERNAME}
 * exists in the dataset but we don't have a Users read port available
 * from Inventory — the fixture uses a deterministic UUID v4 sentinel
 * for `verifiedByUserId`. PurchaseOrder treats the value as opaque.
 */
final class InventoryPurchaseOrdersFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public const REFERENCE_PREFIX = 'inventory.po.';

    private const PO_COUNT = 12;
    private const LINES_PER_PO = 3;
    // First INACTIVE_PO_COUNT POs stay in Draft; the rest are advanced
    // through the full lifecycle.
    private const INACTIVE_PO_COUNT = 6;

    private const VERIFY_USER_ID = '00000000-0000-4000-8000-000000000001';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus,
        private readonly ClockInterface $clock,
        private readonly FixtureReferenceRegistry $references,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $vendorCodes = InventoryVendorsFixtures::vendorCodes();
        $vendorCount = count($vendorCodes);
        $threeDays = DateInterval::createFromDateString('3 days');

        for ($poIndex = 1; $poIndex <= self::PO_COUNT; $poIndex++) {
            $vendorRef = InventoryVendorsFixtures::referenceKey(
                $vendorCodes[($poIndex - 1) % $vendorCount],
            );
            $vendorId = $this->references->get($vendorRef, VendorId::class);

            $facilityCode = ($poIndex % 2) === 0
                ? InventoryBaseFixtures::FACILITY_PRIMARY
                : InventoryBaseFixtures::FACILITY_SECONDARY;

            $lines = $this->buildLines($poIndex);
            $poId = $this->createDraft($vendorId->value, $facilityCode, $lines);
            $this->references->set(self::referenceKey($poIndex), $poId);

            if ($poIndex <= self::INACTIVE_PO_COUNT) {
                continue;
            }

            // Active half: advance through the lifecycle.
            $sentAt = $this->clock->now();
            $estimatedArrival = $sentAt->add($threeDays);
            $this->commandBus->dispatch(new MarkPurchaseOrderSent(
                purchaseOrderId: $poId->value,
                sentAtIso: $sentAt->format(DATE_ATOM),
                estimatedArrivalIso: $estimatedArrival->format(DATE_ATOM),
            ));

            $this->advanceClock($threeDays);

            $detail = $this->fetchPoDetail($poId->value);

            // Partial receive on the first line, full receive on the
            // remaining lines.
            $receivedAt = $this->clock->now();
            foreach ($detail->lines as $lineIndex => $line) {
                $quantity = $lineIndex === 0
                    ? max(1, intdiv($line->orderedUnits, 2))
                    : $line->orderedUnits;
                $this->commandBus->dispatch(new ReceivePurchaseOrderLine(
                    purchaseOrderId: $poId->value,
                    lineId: $line->lineId,
                    receivedQuantityUnits: $quantity,
                    receivedAtIso: $receivedAt->format(DATE_ATOM),
                ));
            }

            // Complete the partial line on a second pass so the
            // ledger shows two RECEIVED rows for that line. Advance
            // the fixture clock first so the second-pass timestamp is
            // strictly after the first under FixedClock — a stale
            // $receivedAt + threeDays would collide with the first
            // pass time in deterministic mode.
            $this->advanceClock($threeDays);
            $secondPassAt = $this->clock->now();
            $detail = $this->fetchPoDetail($poId->value);
            foreach ($detail->lines as $line) {
                if ($line->remainingUnits > 0) {
                    $this->commandBus->dispatch(new ReceivePurchaseOrderLine(
                        purchaseOrderId: $poId->value,
                        lineId: $line->lineId,
                        receivedQuantityUnits: $line->remainingUnits,
                        receivedAtIso: $secondPassAt->format(DATE_ATOM),
                    ));
                }
            }

            // VerifyDelivery requires every line to be fully received;
            // the second pass above guarantees that. The clock is
            // advanced once more so the verifiedAt timestamp is
            // strictly after the last receive.
            $this->advanceClock($threeDays);
            $verifiedAt = $this->clock->now();
            $this->commandBus->dispatch(new VerifyDelivery(
                purchaseOrderId: $poId->value,
                verifiedByUserId: self::VERIFY_USER_ID,
                verifiedAtIso: $verifiedAt->format(DATE_ATOM),
            ));
        }
    }

    public function getDependencies(): array
    {
        return [InventoryStockFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['inventory-pos', 'dev', 'test', 'demo'];
    }

    public static function referenceKey(int $poIndex): string
    {
        return self::REFERENCE_PREFIX . $poIndex;
    }

    // Items reserved by sibling fixtures (1-based indices):
    //   Combos      → 1..14   (4 combos × up to 5 components)
    //   ItemGroups  → 21..32  (6 groups × 2 members; offset by 20)
    //   Links       → 40..55  (8 link pairs × 2 items; offset by 40)
    // POs start at index 56 so PO lines never collide with the
    // domain-meaningful pools above. With 12 POs × 3 lines each,
    // POs cover items 56..91 — comfortably inside the 80-item floor
    // enforced by InventoryStockFixtures::MIN_BULK_COUNT bumped to
    // accommodate this range below.
    private const PO_ITEM_INDEX_OFFSET = 55;

    /**
     * @return list<array{itemId: string, orderedQuantityUnits: int, costPerUnitCents: int}>
     */
    private function buildLines(int $poIndex): array
    {
        $lines = [];
        for ($lineIndex = 0; $lineIndex < self::LINES_PER_PO; $lineIndex++) {
            $itemReferenceIndex = self::PO_ITEM_INDEX_OFFSET
                + (($poIndex - 1) * self::LINES_PER_PO)
                + $lineIndex + 1;
            $itemId = $this->references->get(
                InventoryStockFixtures::referenceKey($itemReferenceIndex),
                InventoryItemId::class,
            );
            $lines[] = [
                'itemId' => $itemId->value,
                'orderedQuantityUnits' => 10 + ($lineIndex * 5) + $poIndex,
                'costPerUnitCents' => 200 + ($lineIndex * 25) + $poIndex,
            ];
        }

        return $lines;
    }

    /**
     * @param list<array{itemId: string, orderedQuantityUnits: int, costPerUnitCents: int}> $lines
     */
    private function createDraft(string $vendorId, string $facilityCode, array $lines): PurchaseOrderId
    {
        $envelope = $this->commandBus->dispatch(new CreatePurchaseOrder(
            vendorId: $vendorId,
            facilityCode: $facilityCode,
            lines: $lines,
        ));

        return HandledResult::from($envelope, PurchaseOrderId::class);
    }

    private function fetchPoDetail(string $purchaseOrderId): PurchaseOrderDetailView
    {
        $envelope = $this->queryBus->dispatch(new GetPurchaseOrderDetail($purchaseOrderId));

        return HandledResult::from($envelope, PurchaseOrderDetailView::class);
    }

    private function advanceClock(DateInterval $interval): void
    {
        if (!$this->clock instanceof FixedClock) {
            // Real clock — nothing to advance. Lifecycle timestamps
            // will reflect actual wall-clock time, which is fine for
            // production-environment fixture loads (none exist today)
            // but precludes byte-identical determinism. Acceptable
            // outside the dev/test env wiring that binds FixedClock.
            return;
        }

        $this->clock->advance($interval);
    }
}
