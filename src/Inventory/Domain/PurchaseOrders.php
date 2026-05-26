<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\PurchaseOrderNotFound;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\VendorId;

/**
 * Domain port for persisting and retrieving PurchaseOrder aggregates.
 *
 * Named finders only — no generic findBy/findOneBy/createQueryBuilder.
 * Open-PO listings exclude Draft, Verified, and Cancelled rows; "open"
 * means actively in flight (Sent or PartiallyReceived).
 */
interface PurchaseOrders
{
    public function add(PurchaseOrder $order): void;

    /**
     * @throws PurchaseOrderNotFound when no order with the given id has
     *         been persisted yet.
     */
    public function save(PurchaseOrder $order): void;

    /**
     * @throws PurchaseOrderNotFound
     */
    public function byId(PurchaseOrderId $id): PurchaseOrder;

    /**
     * @return list<PurchaseOrder>
     */
    public function openByVendor(VendorId $vendorId, int $offset, int $limit): array;

    /**
     * @return list<PurchaseOrder>
     */
    public function byStatus(PurchaseOrderStatus $status, int $offset, int $limit): array;

    /**
     * @return list<PurchaseOrder>
     */
    public function byFacility(FacilityCode $facility, int $offset, int $limit): array;
}
