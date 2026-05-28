<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Fixtures;

use App\Catalog\Application\Command\RegisterListing;
use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Shared\Infrastructure\Fixtures\HandledResult;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Seeds the Catalog bounded context with a small set of non-Inventory
 * listings so the Catalog tables are non-empty even when the
 * --group=catalog-base subset is loaded standalone.
 *
 * Writes flow through {@see RegisterListing} on the command bus — no
 * direct aggregate construction, no EntityManager, no repository
 * calls. Inventory listings are NOT created here; the cross-bus
 * {@see \App\Inventory\Application\Command\RegisterInventoryItem}
 * orchestrator (dispatched by {@see InventoryStockFixtures}) creates
 * both sides atomically.
 */
final class CatalogBaseFixtures extends Fixture implements FixtureGroupInterface
{
    /** @var list<array{code: string, name: string, kind: ListingKind, ledger: string, feeCents: int, feeLabel: string}> */
    private const LISTINGS = [
        [
            'code' => 'MEMBERSHIP-ANNUAL',
            'name' => 'Annual Membership',
            'kind' => ListingKind::Membership,
            'ledger' => '4000',
            'feeCents' => 12_000,
            'feeLabel' => 'Annual dues',
        ],
        [
            'code' => 'MEMBERSHIP-MONTHLY',
            'name' => 'Monthly Membership',
            'kind' => ListingKind::Membership,
            'ledger' => '4000',
            'feeCents' => 1_500,
            'feeLabel' => 'Monthly dues',
        ],
        [
            'code' => 'PROGRAM-SWIM-LESSONS',
            'name' => 'Swim Lessons Program',
            'kind' => ListingKind::Program,
            'ledger' => '4100',
            'feeCents' => 9_500,
            'feeLabel' => 'Per-session fee',
        ],
    ];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::LISTINGS as $row) {
            $this->dispatchListing($row);
        }
    }

    public static function getGroups(): array
    {
        return ['catalog-base', 'dev', 'test', 'demo'];
    }

    /**
     * @param array{code: string, name: string, kind: ListingKind, ledger: string, feeCents: int, feeLabel: string} $row
     */
    private function dispatchListing(array $row): ListingId
    {
        $envelope = $this->commandBus->dispatch(new RegisterListing(
            code: $row['code'],
            kind: $row['kind']->value,
            name: $row['name'],
            fees: [[
                'amountCents' => $row['feeCents'],
                'currency' => 'USD',
                'label' => $row['feeLabel'],
            ]],
            taxApply: false,
            taxIncludedInFee: false,
            ledgerAccount: $row['ledger'],
        ));

        return HandledResult::from($envelope, ListingId::class);
    }
}
