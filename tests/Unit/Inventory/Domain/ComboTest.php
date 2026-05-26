<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Combo;
use App\Inventory\Domain\ComboGraphResolver;
use App\Inventory\Domain\Event\ComboArchived;
use App\Inventory\Domain\Event\ComboComponentsUpdated;
use App\Inventory\Domain\Event\ComboDefined;
use App\Inventory\Domain\Exception\ComboCycleDetected;
use App\Inventory\Domain\Exception\ComboIsArchived;
use App\Inventory\Domain\Exception\ComboMayNotContainCombo;
use App\Inventory\Domain\Exception\ComboRequiresComponents;
use App\Inventory\Domain\Exception\InvalidComboComponent;
use App\Inventory\Domain\ValueObject\ComboComponent;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class ComboTest extends TestCase
{
    private const COMBO_ID = '019571bf-5d51-7000-b500-000000003001';
    private const LISTING_ID = '019571bf-5d51-7000-b500-000000003101';
    private const ITEM_A = '019571bf-5d51-7000-b500-000000003201';
    private const ITEM_B = '019571bf-5d51-7000-b500-000000003202';
    private const ITEM_C = '019571bf-5d51-7000-b500-000000003203';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
    }

    #[Test]
    #[TestDox('::define() records ComboDefined with the components in order.')]
    public function define_happy_path(): void
    {
        $combo = $this->defineCombo([self::ITEM_A, self::ITEM_B], $this->cleanResolver());

        $events = $combo->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(ComboDefined::class, $event);
        self::assertSame(self::COMBO_ID, $event->comboId->value);
        self::assertCount(2, $event->components);
        self::assertSame(self::ITEM_A, $event->components[0]->componentItemId->value);
        self::assertSame(self::ITEM_B, $event->components[1]->componentItemId->value);
        self::assertFalse($combo->isArchived());
    }

    #[Test]
    #[TestDox('::define() with zero components throws ComboRequiresComponents.')]
    public function define_empty_components_throws(): void
    {
        $this->expectException(ComboRequiresComponents::class);

        Combo::define(
            ComboId::fromString(self::COMBO_ID),
            ListingId::fromString(self::LISTING_ID),
            [],
            $this->cleanResolver(),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('ComboComponent with zero quantity-per-combo throws InvalidComboComponent.')]
    public function combo_component_zero_quantity_throws(): void
    {
        $this->expectException(InvalidComboComponent::class);

        // Sonar new_reliability_rating flags an uncaptured `new` expression as
        // "useless object instantiation"; assigning to a local variable that
        // the exception aborts before reading is the documented workaround.
        $unused = new ComboComponent(
            InventoryItemId::fromString(self::ITEM_A),
            Quantity::zero(),
        );
        self::fail(sprintf('Expected InvalidComboComponent before constructing %s.', $unused::class));
    }

    #[Test]
    #[TestDox('::define() rejects a component that is itself a combo (no-nesting rule).')]
    public function define_rejects_nested_combo(): void
    {
        $resolver = $this->resolverWithCombo(self::ITEM_B);

        $this->expectException(ComboMayNotContainCombo::class);

        $this->defineCombo([self::ITEM_A, self::ITEM_B], $resolver);
    }

    #[Test]
    #[TestDox('::define() detects an indirect cycle through the resolver graph (defensive BFS).')]
    public function define_detects_indirect_cycle(): void
    {
        // Resolver claims ITEM_B (a component) expands into ITEM_A — but
        // ITEM_A is already in the combo. The BFS revisits ITEM_A and
        // raises ComboCycleDetected. In production the no-nesting rule
        // makes this configuration unreachable; this exercises the
        // defensive cycle check via a stubbed resolver.
        $itemA = self::ITEM_A;
        $itemB = self::ITEM_B;
        $resolver = new readonly class ($itemA, $itemB) implements ComboGraphResolver {
            public function __construct(private string $itemA, private string $itemB)
            {
            }

            public function isCombo(InventoryItemId $itemId): bool
            {
                return false;
            }

            public function componentsOf(InventoryItemId $itemId): array
            {
                if ($itemId->value === $this->itemB) {
                    return [InventoryItemId::fromString($this->itemA)];
                }
                return [];
            }
        };

        $this->expectException(ComboCycleDetected::class);

        $this->defineCombo([self::ITEM_A, self::ITEM_B], $resolver);
    }

    #[Test]
    #[TestDox('::replaceComponents() records ComboComponentsUpdated when the set changes.')]
    public function replace_components_records_event(): void
    {
        $combo = $this->defineCombo([self::ITEM_A], $this->cleanResolver());
        $combo->releaseEvents();

        $combo->replaceComponents(
            [new ComboComponent(InventoryItemId::fromString(self::ITEM_B), Quantity::ofUnits(2))],
            $this->cleanResolver(),
            $this->clock,
        );

        $events = $combo->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(ComboComponentsUpdated::class, $event);
        self::assertCount(1, $event->components);
        self::assertSame(self::ITEM_B, $event->components[0]->componentItemId->value);
    }

    #[Test]
    #[TestDox('::replaceComponents() is a no-op when the new set value-equals the current one.')]
    public function replace_components_no_op_when_unchanged(): void
    {
        $combo = $this->defineCombo([self::ITEM_A, self::ITEM_B], $this->cleanResolver());
        $combo->releaseEvents();

        $combo->replaceComponents(
            [
                new ComboComponent(InventoryItemId::fromString(self::ITEM_A), Quantity::ofUnits(1)),
                new ComboComponent(InventoryItemId::fromString(self::ITEM_B), Quantity::ofUnits(1)),
            ],
            $this->cleanResolver(),
            $this->clock,
        );

        self::assertSame([], $combo->releaseEvents());
    }

    #[Test]
    #[TestDox('::archive() records ComboArchived and flips the archived flag.')]
    public function archive_records_event(): void
    {
        $combo = $this->defineCombo([self::ITEM_A], $this->cleanResolver());
        $combo->releaseEvents();

        $combo->archive($this->clock);

        $events = $combo->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ComboArchived::class, $events[0]);
        self::assertTrue($combo->isArchived());
    }

    #[Test]
    #[TestDox('::archive() is idempotent — a second call records nothing.')]
    public function archive_is_idempotent(): void
    {
        $combo = $this->defineCombo([self::ITEM_A], $this->cleanResolver());
        $combo->archive($this->clock);
        $combo->releaseEvents();

        $combo->archive($this->clock);

        self::assertSame([], $combo->releaseEvents());
    }

    #[Test]
    #[TestDox('::replaceComponents() on an archived combo throws ComboIsArchived.')]
    public function replace_components_after_archive_throws(): void
    {
        $combo = $this->defineCombo([self::ITEM_A], $this->cleanResolver());
        $combo->archive($this->clock);
        $combo->releaseEvents();

        $this->expectException(ComboIsArchived::class);

        $combo->replaceComponents(
            [new ComboComponent(InventoryItemId::fromString(self::ITEM_C), Quantity::ofUnits(1))],
            $this->cleanResolver(),
            $this->clock,
        );
    }

    /**
     * @param list<string> $itemIds
     */
    private function defineCombo(array $itemIds, ComboGraphResolver $resolver): Combo
    {
        $components = [];
        foreach ($itemIds as $id) {
            $components[] = new ComboComponent(InventoryItemId::fromString($id), Quantity::ofUnits(1));
        }

        return Combo::define(
            ComboId::fromString(self::COMBO_ID),
            ListingId::fromString(self::LISTING_ID),
            $components,
            $resolver,
            $this->clock,
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

    private function resolverWithCombo(string $comboItemId): ComboGraphResolver
    {
        return new readonly class ($comboItemId) implements ComboGraphResolver {
            public function __construct(private string $comboItemId)
            {
            }

            public function isCombo(InventoryItemId $itemId): bool
            {
                return $itemId->value === $this->comboItemId;
            }

            public function componentsOf(InventoryItemId $itemId): array
            {
                return [];
            }
        };
    }
}
