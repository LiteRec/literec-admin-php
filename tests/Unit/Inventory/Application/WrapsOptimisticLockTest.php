<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application;

use App\Inventory\Domain\Exception\ConcurrentInventoryItemModification;
use App\Inventory\Domain\Exception\ConcurrentModification;
use App\Inventory\Domain\Exception\ConcurrentPurchaseOrderModification;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Tests\Support\Fake\PublicWrapsOptimisticLockAdapter;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[Small]
final class WrapsOptimisticLockTest extends TestCase
{
    private const ITEM = '019571bf-5d51-7000-b500-00000000cc01';
    private const PO = '019571bf-5d51-7000-b500-00000000cc02';
    private const PO_LINE = '019571bf-5d51-7000-b500-00000000cc03';

    private PublicWrapsOptimisticLockAdapter $sut;

    protected function setUp(): void
    {
        $this->sut = new PublicWrapsOptimisticLockAdapter();
    }

    #[Test]
    #[TestDox('Inventory wrap: passes through happy path return values.')]
    public function inventory_wrap_passes_through_return_value(): void
    {
        $result = $this->sut->wrapInventoryItemSave(
            InventoryItemId::fromString(self::ITEM),
            static fn (): string => 'persisted',
        );

        self::assertSame('persisted', $result);
    }

    #[Test]
    #[TestDox('Inventory wrap: OptimisticLockException becomes ConcurrentInventoryItemModification with cause preserved.')]
    public function inventory_wrap_translates_optimistic_lock(): void
    {
        $cause = new OptimisticLockException('version mismatch', null);

        $thrown = null;
        try {
            $this->sut->wrapInventoryItemSave(
                InventoryItemId::fromString(self::ITEM),
                static fn () => throw $cause,
            );
        } catch (ConcurrentInventoryItemModification $caught) {
            $thrown = $caught;
        }

        self::assertInstanceOf(ConcurrentInventoryItemModification::class, $thrown);
        self::assertInstanceOf(ConcurrentModification::class, $thrown);
        self::assertSame($cause, $thrown->getPrevious());
        self::assertStringContainsString(self::ITEM, $thrown->getMessage());
    }

    #[Test]
    #[TestDox('Inventory wrap: non-Doctrine exceptions bubble untouched.')]
    public function inventory_wrap_does_not_swallow_other_exceptions(): void
    {
        $cause = new RuntimeException('unrelated failure');

        $thrown = null;
        try {
            $this->sut->wrapInventoryItemSave(
                InventoryItemId::fromString(self::ITEM),
                static fn () => throw $cause,
            );
        } catch (Throwable $caught) {
            $thrown = $caught;
        }

        self::assertSame($cause, $thrown, 'non-Doctrine throwables pass through unmodified');
    }

    #[Test]
    #[TestDox('Purchase order wrap: passes through happy path return values.')]
    public function purchase_order_wrap_passes_through_return_value(): void
    {
        $result = $this->sut->wrapPurchaseOrderSave(
            PurchaseOrderId::fromString(self::PO),
            static fn (): string => 'persisted',
        );

        self::assertSame('persisted', $result);
    }

    #[Test]
    #[TestDox('Purchase order wrap: translates lock to ConcurrentPurchaseOrderModification.')]
    public function purchase_order_wrap_translates_lock(): void
    {
        $cause = new OptimisticLockException('version mismatch', null);

        $thrown = null;
        try {
            $this->sut->wrapPurchaseOrderSave(
                PurchaseOrderId::fromString(self::PO),
                static fn () => throw $cause,
            );
        } catch (ConcurrentPurchaseOrderModification $caught) {
            $thrown = $caught;
        }

        self::assertInstanceOf(ConcurrentPurchaseOrderModification::class, $thrown);
        self::assertInstanceOf(ConcurrentModification::class, $thrown);
        self::assertSame($cause, $thrown->getPrevious());
        self::assertStringContainsString(self::PO, $thrown->getMessage());
    }

    #[Test]
    #[TestDox('Purchase order wrap: non-Doctrine exceptions bubble untouched.')]
    public function purchase_order_wrap_does_not_swallow_other_exceptions(): void
    {
        $cause = new RuntimeException('unrelated failure');

        $thrown = null;
        try {
            $this->sut->wrapPurchaseOrderSave(
                PurchaseOrderId::fromString(self::PO),
                static fn () => throw $cause,
            );
        } catch (Throwable $caught) {
            $thrown = $caught;
        }

        self::assertSame($cause, $thrown, 'non-Doctrine throwables pass through unmodified');
    }

    #[Test]
    #[TestDox('Purchase order line wrap: passes through happy path return values.')]
    public function purchase_order_line_wrap_passes_through_return_value(): void
    {
        $result = $this->sut->wrapPurchaseOrderLineSave(
            PurchaseOrderId::fromString(self::PO),
            PurchaseOrderLineId::fromString(self::PO_LINE),
            static fn (): string => 'persisted',
        );

        self::assertSame('persisted', $result);
    }

    #[Test]
    #[TestDox('Purchase order line wrap: translates lock and names the line in the message.')]
    public function purchase_order_line_wrap_translates_lock(): void
    {
        $cause = new OptimisticLockException('version mismatch', null);

        $thrown = null;
        try {
            $this->sut->wrapPurchaseOrderLineSave(
                PurchaseOrderId::fromString(self::PO),
                PurchaseOrderLineId::fromString(self::PO_LINE),
                static fn () => throw $cause,
            );
        } catch (ConcurrentPurchaseOrderModification $caught) {
            $thrown = $caught;
        }

        self::assertInstanceOf(ConcurrentPurchaseOrderModification::class, $thrown);
        self::assertInstanceOf(ConcurrentModification::class, $thrown);
        self::assertSame($cause, $thrown->getPrevious());
        self::assertStringContainsString(self::PO, $thrown->getMessage());
        self::assertStringContainsString(self::PO_LINE, $thrown->getMessage());
    }

    #[Test]
    #[TestDox('Purchase order line wrap: non-Doctrine exceptions bubble untouched.')]
    public function purchase_order_line_wrap_does_not_swallow_other_exceptions(): void
    {
        $cause = new RuntimeException('unrelated failure');

        $thrown = null;
        try {
            $this->sut->wrapPurchaseOrderLineSave(
                PurchaseOrderId::fromString(self::PO),
                PurchaseOrderLineId::fromString(self::PO_LINE),
                static fn () => throw $cause,
            );
        } catch (Throwable $caught) {
            $thrown = $caught;
        }

        self::assertSame($cause, $thrown, 'non-Doctrine throwables pass through unmodified');
    }
}
