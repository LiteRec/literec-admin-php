<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Combo;
use App\Inventory\Domain\ComboGraphResolver;
use App\Inventory\Domain\Combos;
use App\Inventory\Domain\Exception\ComboNotFound;
use App\Inventory\Domain\ValueObject\ComboComponent;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared behavioural contract for any {@see Combos} adapter. The
 * InMemory and Doctrine drivers both pin to this trait so the
 * implementations cannot drift.
 */
trait CombosContractCases
{
    private const COMBO_A = '019571bf-5d51-7000-b500-000000009a01';
    private const COMBO_B = '019571bf-5d51-7000-b500-000000009a02';
    private const LISTING_A = '019571bf-5d51-7000-b500-000000009b01';
    private const LISTING_B = '019571bf-5d51-7000-b500-000000009b02';
    private const ITEM_X = '019571bf-5d51-7000-b500-000000009c01';
    private const ITEM_Y = '019571bf-5d51-7000-b500-000000009c02';
    private const ITEM_Z = '019571bf-5d51-7000-b500-000000009c03';

    abstract protected function combos(): Combos;

    abstract protected function clock(): MockClock;

    #[Test]
    #[TestDox('add() then byId() round-trips a combo preserving all component quantities.')]
    public function add_then_by_id_round_trips(): void
    {
        $combo = $this->defineCombo(
            self::COMBO_A,
            self::LISTING_A,
            [
                [self::ITEM_X, 1],
                [self::ITEM_Y, 2],
                [self::ITEM_Z, 3],
            ],
        );
        $combo->releaseEvents();
        $this->combos()->add($combo);

        $loaded = $this->combos()->byId(ComboId::fromString(self::COMBO_A));

        self::assertSame(self::LISTING_A, $loaded->listingId()->value);
        $components = $loaded->components();
        self::assertCount(3, $components);
        $quantitiesByItem = [];
        foreach ($components as $c) {
            $quantitiesByItem[$c->componentItemId->value] = $c->quantityPerCombo->units;
        }
        self::assertSame(1, $quantitiesByItem[self::ITEM_X]);
        self::assertSame(2, $quantitiesByItem[self::ITEM_Y]);
        self::assertSame(3, $quantitiesByItem[self::ITEM_Z]);
    }

    #[Test]
    #[TestDox('byListingId() returns the combo registered for a catalog listing.')]
    public function by_listing_id_returns_matching_combo(): void
    {
        $combo = $this->defineCombo(self::COMBO_A, self::LISTING_A, [[self::ITEM_X, 1]]);
        $combo->releaseEvents();
        $this->combos()->add($combo);

        $loaded = $this->combos()->byListingId(ListingId::fromString(self::LISTING_A));

        self::assertSame(self::COMBO_A, $loaded->id()->value);
    }

    #[Test]
    #[TestDox('byListingId() throws ComboNotFound when no combo matches the listing.')]
    public function by_listing_id_throws_when_missing(): void
    {
        $this->expectException(ComboNotFound::class);
        $this->combos()->byListingId(ListingId::fromString(self::LISTING_B));
    }

    #[Test]
    #[TestDox('forComponent() returns every combo whose components include the item.')]
    public function for_component_returns_all_matching_combos(): void
    {
        $first = $this->defineCombo(
            self::COMBO_A,
            self::LISTING_A,
            [[self::ITEM_X, 1], [self::ITEM_Y, 2]],
        );
        $first->releaseEvents();
        $this->combos()->add($first);

        $second = $this->defineCombo(
            self::COMBO_B,
            self::LISTING_B,
            [[self::ITEM_X, 4]],
        );
        $second->releaseEvents();
        $this->combos()->add($second);

        $result = $this->combos()->forComponent(InventoryItemId::fromString(self::ITEM_X));

        $ids = array_map(static fn (Combo $c): string => $c->id()->value, $result);
        sort($ids);
        self::assertSame([self::COMBO_A, self::COMBO_B], $ids);
    }

    #[Test]
    #[TestDox('save() after replaceComponents() persists the new component set and clears the dropped row.')]
    public function save_persists_replaced_components(): void
    {
        $combo = $this->defineCombo(
            self::COMBO_A,
            self::LISTING_A,
            [[self::ITEM_X, 1], [self::ITEM_Y, 1]],
        );
        $combo->releaseEvents();
        $this->combos()->add($combo);

        $loaded = $this->combos()->byId(ComboId::fromString(self::COMBO_A));
        $loaded->replaceComponents(
            [
                new ComboComponent(InventoryItemId::fromString(self::ITEM_X), Quantity::ofUnits(5)),
                new ComboComponent(InventoryItemId::fromString(self::ITEM_Z), Quantity::ofUnits(2)),
            ],
            $this->cleanResolver(),
            $this->clock(),
        );
        $loaded->releaseEvents();
        $this->combos()->save($loaded);

        $reloaded = $this->combos()->byId(ComboId::fromString(self::COMBO_A));
        $byItem = [];
        foreach ($reloaded->components() as $c) {
            $byItem[$c->componentItemId->value] = $c->quantityPerCombo->units;
        }
        self::assertCount(2, $byItem);
        self::assertSame(5, $byItem[self::ITEM_X]);
        self::assertSame(2, $byItem[self::ITEM_Z]);
        self::assertArrayNotHasKey(self::ITEM_Y, $byItem);
    }

    /**
     * @param list<array{0: string, 1: int}> $components item-id / qty pairs
     */
    private function defineCombo(string $comboId, string $listingId, array $components): Combo
    {
        return Combo::define(
            ComboId::fromString($comboId),
            ListingId::fromString($listingId),
            array_map(
                static fn (array $pair): ComboComponent => new ComboComponent(
                    InventoryItemId::fromString($pair[0]),
                    Quantity::ofUnits($pair[1]),
                ),
                $components,
            ),
            $this->cleanResolver(),
            $this->clock(),
        );
    }

    private function cleanResolver(): ComboGraphResolver
    {
        return new readonly class implements ComboGraphResolver {
            public function isCombo(InventoryItemId $itemId): bool
            {
                return false;
            }

            public function componentsOf(InventoryItemId $itemId): array
            {
                return [];
            }
        };
    }
}
