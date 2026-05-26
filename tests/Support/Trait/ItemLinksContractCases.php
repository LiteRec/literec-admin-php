<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Inventory\Domain\Exception\DuplicateItemLink;
use App\Inventory\Domain\Exception\ItemLinkNotFound;
use App\Inventory\Domain\ItemLink;
use App\Inventory\Domain\ItemLinks;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use App\Inventory\Domain\ValueObject\Quantity;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared behavioural contract for any {@see ItemLinks} adapter. The
 * InMemory and Doctrine drivers both pin to this trait so the
 * implementations cannot drift.
 */
trait ItemLinksContractCases
{
    private const LINK_A = '019571bf-5d51-7000-b500-000000007a01';
    private const LINK_B = '019571bf-5d51-7000-b500-000000007a02';
    private const MASTER_A = '019571bf-5d51-7000-b500-000000007b01';
    private const LINKED = '019571bf-5d51-7000-b500-000000007c01';

    abstract protected function itemLinks(): ItemLinks;

    abstract protected function clock(): MockClock;

    #[Test]
    #[TestDox('add() then byId() round-trips every persisted field, including a non-null includeUntil.')]
    public function add_then_by_id_round_trips(): void
    {
        $includeUntil = new DateTimeImmutable('2027-01-01 09:30:00');
        $link = $this->makeLink(
            self::LINK_A,
            self::MASTER_A,
            self::LINKED,
            reserved: 5,
            min: 1,
            max: 3,
            includeUntil: $includeUntil,
        );
        $this->itemLinks()->add($link);

        $loaded = $this->itemLinks()->byId(ItemLinkId::fromString(self::LINK_A));

        self::assertTrue($loaded->id()->equals($link->id()));
        self::assertTrue($loaded->masterItemId()->equals($link->masterItemId()));
        self::assertTrue($loaded->linkedItemId()->equals($link->linkedItemId()));
        self::assertSame(5, $loaded->reservedQuantity()->units);
        self::assertSame(1, $loaded->minRequired()->units);
        self::assertSame(3, $loaded->maxPerPurchase()->units);
        self::assertFalse($loaded->unlimited());
        self::assertNotNull($loaded->includeUntil());
        self::assertSame($includeUntil->getTimestamp(), $loaded->includeUntil()->getTimestamp());
    }

    #[Test]
    #[TestDox('byId() throws ItemLinkNotFound when no link with the given id exists.')]
    public function by_id_missing_throws(): void
    {
        $this->expectException(ItemLinkNotFound::class);

        $this->itemLinks()->byId(ItemLinkId::fromString(self::LINK_A));
    }

    #[Test]
    #[TestDox('byPair() returns the link for a given (master, linked) pair.')]
    public function by_pair_returns_link(): void
    {
        $this->itemLinks()->add($this->makeLink(self::LINK_A, self::MASTER_A, self::LINKED));

        $loaded = $this->itemLinks()->byPair(
            InventoryItemId::fromString(self::MASTER_A),
            InventoryItemId::fromString(self::LINKED),
        );

        self::assertSame(self::LINK_A, $loaded->id()->value);
    }

    #[Test]
    #[TestDox('existsForPair() reflects whether a (master, linked) pair has been added.')]
    public function exists_for_pair(): void
    {
        $master = InventoryItemId::fromString(self::MASTER_A);
        $linked = InventoryItemId::fromString(self::LINKED);

        self::assertFalse($this->itemLinks()->existsForPair($master, $linked));

        $this->itemLinks()->add($this->makeLink(self::LINK_A, self::MASTER_A, self::LINKED));

        self::assertTrue($this->itemLinks()->existsForPair($master, $linked));
    }

    #[Test]
    #[TestDox('add() throws DuplicateItemLink when a second link targets the same (master, linked) pair.')]
    public function add_duplicate_pair_throws(): void
    {
        $this->itemLinks()->add($this->makeLink(self::LINK_A, self::MASTER_A, self::LINKED));

        $this->expectException(DuplicateItemLink::class);

        $this->itemLinks()->add($this->makeLink(self::LINK_B, self::MASTER_A, self::LINKED));
    }

    #[Test]
    #[TestDox('save() persists subsequent updates after the link has been added.')]
    public function save_persists_updates(): void
    {
        $link = $this->makeLink(self::LINK_A, self::MASTER_A, self::LINKED, reserved: 5);
        $this->itemLinks()->add($link);

        $link->update(
            Quantity::ofUnits(10),
            true,
            Quantity::ofUnits(2),
            Quantity::ofUnits(4),
            null,
            $this->clock(),
        );
        $this->itemLinks()->save($link);

        $loaded = $this->itemLinks()->byId(ItemLinkId::fromString(self::LINK_A));
        self::assertSame(10, $loaded->reservedQuantity()->units);
        self::assertTrue($loaded->unlimited());
    }

    #[Test]
    #[TestDox('remove() deletes the link; subsequent byId() throws ItemLinkNotFound.')]
    public function remove_deletes_link(): void
    {
        $link = $this->makeLink(self::LINK_A, self::MASTER_A, self::LINKED);
        $this->itemLinks()->add($link);

        $this->itemLinks()->remove($link);

        $this->expectException(ItemLinkNotFound::class);
        $this->itemLinks()->byId(ItemLinkId::fromString(self::LINK_A));
    }

    #[Test]
    #[TestDox('activeForMaster() filters out expired includeUntil but keeps links whose includeUntil equals now.')]
    public function active_for_master_filters_expired(): void
    {
        $now = new DateTimeImmutable('2026-05-26 12:00:00');
        $boundary = '019571bf-5d51-7000-b500-000000007a03';

        $active = $this->makeLink(self::LINK_A, self::MASTER_A, self::LINKED);
        $expired = $this->makeLink(
            self::LINK_B,
            self::MASTER_A,
            '019571bf-5d51-7000-b500-000000007c02',
            includeUntil: new DateTimeImmutable('2025-01-01 00:00:00'),
        );
        $exactlyNow = $this->makeLink(
            $boundary,
            self::MASTER_A,
            '019571bf-5d51-7000-b500-000000007c03',
            includeUntil: $now,
        );
        $this->itemLinks()->add($active);
        $this->itemLinks()->add($expired);
        $this->itemLinks()->add($exactlyNow);

        $rows = $this->itemLinks()->activeForMaster(InventoryItemId::fromString(self::MASTER_A), $now);
        $ids = array_map(static fn (ItemLink $link): string => $link->id()->value, $rows);

        self::assertContains(self::LINK_A, $ids);
        self::assertContains($boundary, $ids, 'includeUntil == now is still active');
        self::assertNotContains(self::LINK_B, $ids, 'expired include_until must be filtered out');
    }

    private function makeLink(
        string $linkId,
        string $masterId,
        string $linkedId,
        int $reserved = 0,
        bool $unlimited = false,
        int $min = 0,
        int $max = 0,
        ?DateTimeImmutable $includeUntil = null,
    ): ItemLink {
        return ItemLink::link(
            ItemLinkId::fromString($linkId),
            InventoryItemId::fromString($masterId),
            InventoryItemId::fromString($linkedId),
            Quantity::ofUnits($reserved),
            $unlimited,
            Quantity::ofUnits($min),
            Quantity::ofUnits($max),
            $includeUntil,
            $this->clock(),
        );
    }
}
