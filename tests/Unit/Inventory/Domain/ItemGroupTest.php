<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Inventory\Domain\Event\ItemAddedToGroup;
use App\Inventory\Domain\Event\ItemGroupArchived;
use App\Inventory\Domain\Event\ItemGroupCreated;
use App\Inventory\Domain\Event\ItemGroupRecolored;
use App\Inventory\Domain\Event\ItemGroupRenamed;
use App\Inventory\Domain\Event\ItemRemovedFromGroup;
use App\Inventory\Domain\Exception\ItemGroupArchived as ItemGroupArchivedException;
use App\Inventory\Domain\ItemGroup;
use App\Inventory\Domain\ValueObject\FacilityScope;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemGroupName;
use App\Inventory\Domain\ValueObject\PosColor;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class ItemGroupTest extends TestCase
{
    private const GROUP_ID = '019571bf-5d51-7000-b500-000000004001';
    private const ITEM_A = '019571bf-5d51-7000-b500-000000004101';
    private const ITEM_B = '019571bf-5d51-7000-b500-000000004102';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
    }

    #[Test]
    #[TestDox('::create() records ItemGroupCreated with the supplied name, color, and scope.')]
    public function create_records_event(): void
    {
        $group = $this->createGroup(FacilityScope::all());

        $events = $group->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(ItemGroupCreated::class, $event);
        self::assertSame('Beverages', $event->name->value);
        self::assertTrue($event->facilityScope->isAll());
        self::assertFalse($group->isArchived());
    }

    #[Test]
    #[TestDox('::addItem() records ItemAddedToGroup and the member appears in hasMember()/members().')]
    public function add_item_records_event(): void
    {
        $group = $this->createGroup(FacilityScope::all());
        $group->releaseEvents();

        $group->addItem(InventoryItemId::fromString(self::ITEM_A), $this->clock);

        $events = $group->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ItemAddedToGroup::class, $events[0]);
        self::assertTrue($group->hasMember(InventoryItemId::fromString(self::ITEM_A)));
    }

    #[Test]
    #[TestDox('::addItem() is idempotent — adding the same item again records nothing.')]
    public function add_item_idempotent(): void
    {
        $group = $this->createGroup(FacilityScope::all());
        $group->addItem(InventoryItemId::fromString(self::ITEM_A), $this->clock);
        $group->releaseEvents();

        $group->addItem(InventoryItemId::fromString(self::ITEM_A), $this->clock);

        self::assertSame([], $group->releaseEvents());
    }

    #[Test]
    #[TestDox('::addItem() on an archived group throws ItemGroupArchived but leaves existing members.')]
    public function add_item_after_archive_throws(): void
    {
        $group = $this->createGroup(FacilityScope::all());
        $group->addItem(InventoryItemId::fromString(self::ITEM_A), $this->clock);
        $group->archive($this->clock);
        $group->releaseEvents();

        try {
            $group->addItem(InventoryItemId::fromString(self::ITEM_B), $this->clock);
            self::fail('Expected ItemGroupArchived.');
        } catch (ItemGroupArchivedException) {
            // ok
        }

        self::assertTrue(
            $group->hasMember(InventoryItemId::fromString(self::ITEM_A)),
            'archive must preserve existing members for historical reporting',
        );
        self::assertFalse($group->hasMember(InventoryItemId::fromString(self::ITEM_B)));
    }

    #[Test]
    #[TestDox('::removeItem() records ItemRemovedFromGroup and works on archived groups.')]
    public function remove_item_records_event(): void
    {
        $group = $this->createGroup(FacilityScope::all());
        $group->addItem(InventoryItemId::fromString(self::ITEM_A), $this->clock);
        $group->archive($this->clock);
        $group->releaseEvents();

        // remove on archived group is allowed so admins can prune.
        $group->removeItem(InventoryItemId::fromString(self::ITEM_A), $this->clock);

        $events = $group->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ItemRemovedFromGroup::class, $events[0]);
        self::assertFalse($group->hasMember(InventoryItemId::fromString(self::ITEM_A)));
    }

    #[Test]
    #[TestDox('::rename() / ::recolor() emit events on change and short-circuit when unchanged.')]
    public function rename_and_recolor_short_circuit(): void
    {
        $group = $this->createGroup(FacilityScope::all());
        $group->releaseEvents();

        $group->rename(ItemGroupName::of('Drinks'), $this->clock);
        $group->recolor(PosColor::ofHex('#112233'), $this->clock);

        $events = $group->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(ItemGroupRenamed::class, $events[0]);
        self::assertInstanceOf(ItemGroupRecolored::class, $events[1]);

        // Re-applying the same values is a no-op.
        $group->rename(ItemGroupName::of('Drinks'), $this->clock);
        $group->recolor(PosColor::ofHex('#112233'), $this->clock);
        self::assertSame([], $group->releaseEvents());
    }

    #[Test]
    #[TestDox('::rename() on an archived group throws ItemGroupArchived.')]
    public function rename_on_archived_throws(): void
    {
        $group = $this->createGroup(FacilityScope::all());
        $group->archive($this->clock);
        $group->releaseEvents();

        $this->expectException(ItemGroupArchivedException::class);

        $group->rename(ItemGroupName::of('Other'), $this->clock);
    }

    #[Test]
    #[TestDox('::recolor() on an archived group throws ItemGroupArchived.')]
    public function recolor_on_archived_throws(): void
    {
        $group = $this->createGroup(FacilityScope::all());
        $group->archive($this->clock);
        $group->releaseEvents();

        $this->expectException(ItemGroupArchivedException::class);

        $group->recolor(PosColor::ofHex('#000000'), $this->clock);
    }

    #[Test]
    #[TestDox('::archive() records ItemGroupArchived and is idempotent.')]
    public function archive_records_event_idempotent(): void
    {
        $group = $this->createGroup(FacilityScope::all());
        $group->releaseEvents();

        $group->archive($this->clock);
        $events = $group->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ItemGroupArchived::class, $events[0]);
        self::assertTrue($group->isArchived());

        $group->archive($this->clock);
        self::assertSame([], $group->releaseEvents());
    }

    private function createGroup(FacilityScope $scope): ItemGroup
    {
        return ItemGroup::create(
            ItemGroupId::fromString(self::GROUP_ID),
            ItemGroupName::of('Beverages'),
            PosColor::ofHex('#FF8800'),
            $scope,
            $this->clock,
        );
    }
}
