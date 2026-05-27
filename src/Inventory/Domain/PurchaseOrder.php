<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Event\PurchaseOrderDrafted;
use App\Inventory\Domain\Event\PurchaseOrderFullyReceived;
use App\Inventory\Domain\Event\PurchaseOrderLineReceived;
use App\Inventory\Domain\Event\PurchaseOrderSent;
use App\Inventory\Domain\Event\PurchaseOrderVerified;
use App\Inventory\Domain\Exception\PurchaseOrderLineNotFound;
use App\Inventory\Domain\Exception\PurchaseOrderNotDraft;
use App\Inventory\Domain\Exception\PurchaseOrderNotFullyReceived;
use App\Inventory\Domain\Exception\PurchaseOrderNotSent;
use App\Inventory\Domain\Exception\PurchaseOrderRequiresLines;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineDraft;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\VendorId;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Psr\Clock\ClockInterface;

/**
 * Procurement aggregate: a single PurchaseOrder placed with a Vendor at
 * a Facility, with one or more {@see PurchaseOrderLine} children.
 *
 * Lifecycle: Draft → Sent → PartiallyReceived → FullyReceived → Verified.
 * Cancelled is a terminal alternative from Draft/Sent (not modelled here
 * yet; the enum case is reserved for the cancellation flow LRA-90 will
 * trigger from the UI).
 *
 * Stock-batch creation from a received line is the cross-aggregate flow
 * handled by the LRA-79 application service: it subscribes to
 * {@see PurchaseOrderLineReceived} and calls
 * {@see InventoryItem::receiveBatch()} on the referenced item.
 */
final class PurchaseOrder
{
    use AggregateRoot;

    private PurchaseOrderId $id;
    private VendorId $vendorId;
    private FacilityCode $facilityCode;
    private PurchaseOrderStatus $status;
    private ?DateTimeImmutable $sentAt;
    private ?DateTimeImmutable $estimatedArrival;
    private ?string $verifiedByUserId;
    private ?DateTimeImmutable $verifiedAt;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    /**
     * Doctrine optimistic-lock version (LRA-99). Maintained by the
     * ORM; never mutated by domain code. Concurrent saves surface as
     * {@see \Doctrine\ORM\OptimisticLockException} which the
     * application-side WrapsOptimisticLock trait translates into
     * {@see \App\Inventory\Domain\Exception\ConcurrentPurchaseOrderModification}.
     *
     * Exposed via {@see version()} so tests can pin the increment
     * across save operations and so PHPStan sees the property used.
     */
    private int $version = 0;

    /** @var Collection<int, PurchaseOrderLine> */
    private Collection $lines;

    private function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    /**
     * @param list<PurchaseOrderLineDraft> $lines
     */
    public static function createDraft(
        PurchaseOrderId $id,
        VendorId $vendorId,
        FacilityCode $facilityCode,
        array $lines,
        ClockInterface $clock,
    ): self {
        if ($lines === []) {
            throw PurchaseOrderRequiresLines::empty();
        }

        $order = new self();
        $order->id = $id;
        $order->vendorId = $vendorId;
        $order->facilityCode = $facilityCode;
        $order->status = PurchaseOrderStatus::Draft;
        $order->sentAt = null;
        $order->estimatedArrival = null;
        $order->verifiedByUserId = null;
        $order->verifiedAt = null;
        $order->createdAt = $clock->now();
        $order->updatedAt = $order->createdAt;

        foreach ($lines as $draft) {
            $line = PurchaseOrderLine::create(
                $draft->lineId,
                $draft->itemId,
                $draft->orderedQuantity,
                $draft->costPerUnit,
                $order->createdAt,
            );
            $line->attachToOrder($order);
            $order->lines->add($line);
        }

        $order->recordThat(new PurchaseOrderDrafted(
            $id,
            $vendorId,
            $facilityCode,
            $lines,
            $order->createdAt,
        ));

        return $order;
    }

    public function id(): PurchaseOrderId
    {
        return $this->id;
    }

    public function vendorId(): VendorId
    {
        return $this->vendorId;
    }

    public function facilityCode(): FacilityCode
    {
        return $this->facilityCode;
    }

    public function status(): PurchaseOrderStatus
    {
        return $this->status;
    }

    public function sentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function estimatedArrival(): ?DateTimeImmutable
    {
        return $this->estimatedArrival;
    }

    public function verifiedByUserId(): ?string
    {
        return $this->verifiedByUserId;
    }

    public function verifiedAt(): ?DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * @return list<PurchaseOrderLine>
     */
    public function lines(): array
    {
        $arr = iterator_to_array($this->lines, false);
        usort(
            $arr,
            static fn (PurchaseOrderLine $a, PurchaseOrderLine $b): int =>
                $a->createdAt() <=> $b->createdAt()
                    ?: strcmp($a->id()->value, $b->id()->value),
        );
        return $arr;
    }

    public function send(
        DateTimeImmutable $sentAt,
        ?DateTimeImmutable $estimatedArrival,
        ClockInterface $clock,
    ): void {
        if ($this->status !== PurchaseOrderStatus::Draft) {
            throw PurchaseOrderNotDraft::for($this->id, $this->status);
        }

        $this->status = PurchaseOrderStatus::Sent;
        $this->sentAt = $sentAt;
        $this->estimatedArrival = $estimatedArrival;
        $this->updatedAt = $clock->now();

        $this->recordThat(new PurchaseOrderSent(
            $this->id,
            $sentAt,
            $estimatedArrival,
            $this->updatedAt,
        ));
    }

    public function receiveLine(
        PurchaseOrderLineId $lineId,
        Quantity $quantity,
        DateTimeImmutable $receivedAt,
        ClockInterface $clock,
    ): void {
        if (
            $this->status !== PurchaseOrderStatus::Sent
            && $this->status !== PurchaseOrderStatus::PartiallyReceived
        ) {
            throw PurchaseOrderNotSent::for($this->id, $this->status);
        }

        $line = $this->findLine($lineId);
        $line->receive($quantity);

        $this->updatedAt = $clock->now();

        $this->recordThat(new PurchaseOrderLineReceived(
            $this->id,
            $lineId,
            $line->itemId(),
            $this->facilityCode,
            $quantity,
            $line->costPerUnit(),
            $receivedAt,
            $this->updatedAt,
        ));

        if ($this->allLinesFullyReceived()) {
            $this->status = PurchaseOrderStatus::FullyReceived;
            $this->recordThat(new PurchaseOrderFullyReceived($this->id, $this->updatedAt));
        } else {
            $this->status = PurchaseOrderStatus::PartiallyReceived;
        }
    }

    public function verifyDelivery(
        string $verifiedByUserId,
        DateTimeImmutable $verifiedAt,
        ClockInterface $clock,
    ): void {
        if ($this->status !== PurchaseOrderStatus::FullyReceived) {
            throw PurchaseOrderNotFullyReceived::for($this->id);
        }

        $this->status = PurchaseOrderStatus::Verified;
        $this->verifiedByUserId = $verifiedByUserId;
        $this->verifiedAt = $verifiedAt;
        $this->updatedAt = $clock->now();

        $this->recordThat(new PurchaseOrderVerified(
            $this->id,
            $verifiedByUserId,
            $verifiedAt,
            $this->updatedAt,
        ));
    }

    private function findLine(PurchaseOrderLineId $lineId): PurchaseOrderLine
    {
        foreach ($this->lines as $line) {
            if ($line->id()->equals($lineId)) {
                return $line;
            }
        }

        throw PurchaseOrderLineNotFound::for($this->id, $lineId);
    }

    private function allLinesFullyReceived(): bool
    {
        foreach ($this->lines as $line) {
            if (! $line->isFullyReceived()) {
                return false;
            }
        }
        return true;
    }
}
