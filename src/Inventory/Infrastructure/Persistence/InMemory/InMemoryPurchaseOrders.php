<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\InMemory;

use App\Inventory\Domain\Exception\PurchaseOrderNotFound;
use App\Inventory\Domain\PurchaseOrder;
use App\Inventory\Domain\PurchaseOrders;
use App\Inventory\Domain\PurchaseOrderStatus;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\VendorId;

/**
 * Array-backed adapter for the {@see PurchaseOrders} port. Used by
 * Application-layer unit tests and the shared contract test so the
 * Domain layer can run without a live database.
 */
final class InMemoryPurchaseOrders implements PurchaseOrders
{
    /** @var array<string, PurchaseOrder> keyed by purchase order id string */
    private array $byId = [];

    public function add(PurchaseOrder $order): void
    {
        $this->byId[$order->id()->value] = $order;
    }

    public function save(PurchaseOrder $order): void
    {
        if (! isset($this->byId[$order->id()->value])) {
            throw PurchaseOrderNotFound::withId($order->id());
        }

        $this->byId[$order->id()->value] = $order;
    }

    public function byId(PurchaseOrderId $id): PurchaseOrder
    {
        if (! isset($this->byId[$id->value])) {
            throw PurchaseOrderNotFound::withId($id);
        }

        return $this->byId[$id->value];
    }

    public function openByVendor(VendorId $vendorId, int $offset, int $limit): array
    {
        return $this->sliceSorted(
            array_filter(
                $this->byId,
                static fn (PurchaseOrder $o): bool =>
                    $o->vendorId()->equals($vendorId)
                    && in_array(
                        $o->status(),
                        [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived],
                        true,
                    ),
            ),
            $offset,
            $limit,
        );
    }

    public function byStatus(PurchaseOrderStatus $status, int $offset, int $limit): array
    {
        return $this->sliceSorted(
            array_filter($this->byId, static fn (PurchaseOrder $o): bool => $o->status() === $status),
            $offset,
            $limit,
        );
    }

    public function byFacility(FacilityCode $facility, int $offset, int $limit): array
    {
        return $this->sliceSorted(
            array_filter(
                $this->byId,
                static fn (PurchaseOrder $o): bool => $o->facilityCode()->equals($facility),
            ),
            $offset,
            $limit,
        );
    }

    public function search(
        ?VendorId $vendorId,
        ?PurchaseOrderStatus $status,
        ?FacilityCode $facility,
        int $offset,
        int $limit,
    ): array {
        return $this->sliceSorted($this->filterCombined($vendorId, $status, $facility), $offset, $limit);
    }

    public function countMatching(
        ?VendorId $vendorId,
        ?PurchaseOrderStatus $status,
        ?FacilityCode $facility,
    ): int {
        return count($this->filterCombined($vendorId, $status, $facility));
    }

    /**
     * @return array<string, PurchaseOrder>
     */
    private function filterCombined(
        ?VendorId $vendorId,
        ?PurchaseOrderStatus $status,
        ?FacilityCode $facility,
    ): array {
        return array_filter(
            $this->byId,
            static function (PurchaseOrder $o) use ($vendorId, $status, $facility): bool {
                if ($vendorId !== null && ! $o->vendorId()->equals($vendorId)) {
                    return false;
                }
                if ($status !== null && $o->status() !== $status) {
                    return false;
                }
                if ($facility !== null && ! $o->facilityCode()->equals($facility)) {
                    return false;
                }
                return true;
            },
        );
    }

    /**
     * @param array<string, PurchaseOrder> $filtered
     * @return list<PurchaseOrder>
     */
    private function sliceSorted(array $filtered, int $offset, int $limit): array
    {
        $sorted = array_values($filtered);
        usort(
            $sorted,
            static fn (PurchaseOrder $a, PurchaseOrder $b): int =>
                $b->createdAt() <=> $a->createdAt()
                    ?: strcmp($a->id()->value, $b->id()->value),
        );
        return array_slice($sorted, $offset, $limit);
    }
}
