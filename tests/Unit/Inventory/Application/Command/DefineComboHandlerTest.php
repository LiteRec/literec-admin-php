<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Application\Command\DefineCombo;
use App\Inventory\Application\Command\DefineComboHandler;
use App\Inventory\Domain\ComboGraphResolver;
use App\Inventory\Domain\Event\ComboDefined;
use App\Inventory\Domain\Exception\ComboMayNotContainCombo;
use App\Inventory\Domain\Exception\ComboRequiresComponents;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryCombos;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Fake\SequenceInventoryIdentityGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class DefineComboHandlerTest extends TestCase
{
    private const COMBO_ID = '019571bf-5d51-7000-b500-000000003a01';
    private const LISTING_ID = '019571bf-5d51-7000-b500-000000003a02';
    private const ITEM_A = '019571bf-5d51-7000-b500-000000003a03';
    private const ITEM_B = '019571bf-5d51-7000-b500-000000003a04';

    private InMemoryCombos $combos;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->combos = new InMemoryCombos();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
    }

    #[Test]
    #[TestDox('Persists the combo, returns its id, and dispatches ComboDefined.')]
    public function happy_path(): void
    {
        $ids = new SequenceInventoryIdentityGenerator(
            comboIds: [ComboId::fromString(self::COMBO_ID)],
        );
        $handler = new DefineComboHandler(
            $this->combos,
            $this->cleanResolver(),
            $ids,
            $this->clock,
            $this->eventBus,
        );

        $comboId = ($handler)(new DefineCombo(
            listingId: self::LISTING_ID,
            components: [
                ['itemId' => self::ITEM_A, 'quantityPerCombo' => 1],
                ['itemId' => self::ITEM_B, 'quantityPerCombo' => 2],
            ],
        ));

        self::assertSame(self::COMBO_ID, $comboId->value);

        $loaded = $this->combos->byId($comboId);
        self::assertCount(2, $loaded->components());

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(ComboDefined::class, $messages[0]);
    }

    #[Test]
    #[TestDox('Empty component list throws ComboRequiresComponents before any persistence.')]
    public function empty_components_throws(): void
    {
        $handler = new DefineComboHandler(
            $this->combos,
            $this->cleanResolver(),
            new SequenceInventoryIdentityGenerator(),
            $this->clock,
            $this->eventBus,
        );

        $this->expectException(ComboRequiresComponents::class);

        ($handler)(new DefineCombo(listingId: self::LISTING_ID, components: []));
    }

    #[Test]
    #[TestDox('Nested combo component throws ComboMayNotContainCombo (no-nesting rule).')]
    public function nested_combo_throws(): void
    {
        $itemA = self::ITEM_A;
        $resolver = new readonly class ($itemA) implements ComboGraphResolver {
            public function __construct(private string $itemA)
            {
            }

            public function isCombo(InventoryItemId $itemId): bool
            {
                return $itemId->value === $this->itemA;
            }

            public function componentsOf(InventoryItemId $itemId): array
            {
                return [];
            }
        };

        $ids = new SequenceInventoryIdentityGenerator(
            comboIds: [ComboId::fromString(self::COMBO_ID)],
        );
        $handler = new DefineComboHandler($this->combos, $resolver, $ids, $this->clock, $this->eventBus);

        $this->expectException(ComboMayNotContainCombo::class);

        ($handler)(new DefineCombo(
            listingId: self::LISTING_ID,
            components: [['itemId' => self::ITEM_A, 'quantityPerCombo' => 1]],
        ));
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
