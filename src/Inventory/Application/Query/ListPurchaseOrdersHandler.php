<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

use App\Inventory\Application\Query\View\PurchaseOrderListPage;
use App\Inventory\Application\Query\View\PurchaseOrderSummaryView;
use App\Inventory\Domain\PurchaseOrder;
use App\Inventory\Domain\PurchaseOrders;
use App\Inventory\Domain\PurchaseOrderStatus;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\VendorId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Read-side handler for the LRA-90 Purchase Orders list page.
 *
 * Projects matched {@see PurchaseOrder} aggregates into flat
 * {@see PurchaseOrderSummaryView} rows with pre-computed totals so the
 * Twig template stays free of arithmetic. Status filter strings that
 * do not map to a known enum case disable the status dimension rather
 * than 400-ing the page.
 */
#[AsMessageHandler(bus: 'query.bus')]
final class ListPurchaseOrdersHandler
{
    public function __construct(
        private readonly PurchaseOrders $purchaseOrders,
    ) {
    }

    public function __invoke(ListPurchaseOrders $query): PurchaseOrderListPage
    {
        // Graceful-degrade on malformed filter input: an unparseable
        // vendor or facility filter disables that dimension rather
        // than 500-ing the page (mirrors the PurchaseOrderStatus::tryFrom
        // behaviour for the status filter).
        // Clamp page params at the handler boundary so non-HTTP callers
        // (Messenger, tests, fixtures) can't trigger unbounded reads or
        // negative offsets.
        $pageNumber = max(1, $query->pageNumber);
        $pageSize = min(200, max(1, $query->pageSize));

        $vendor = self::tryVendor($query->vendorId);
        $status = $query->status !== null ? PurchaseOrderStatus::tryFrom($query->status) : null;
        $facility = self::tryFacility($query->facilityCode);

        $offset = ($pageNumber - 1) * $pageSize;

        $rows = $this->purchaseOrders->search($vendor, $status, $facility, $offset, $pageSize);
        $totalCount = $this->purchaseOrders->countMatching($vendor, $status, $facility);

        $items = [];
        foreach ($rows as $order) {
            $items[] = self::summaryFrom($order);
        }

        return new PurchaseOrderListPage(
            items: $items,
            totalCount: $totalCount,
            pageNumber: $pageNumber,
            pageSize: $pageSize,
        );
    }

    private static function tryVendor(?string $raw): ?VendorId
    {
        if ($raw === null) {
            return null;
        }
        try {
            return VendorId::fromString($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function tryFacility(?string $raw): ?FacilityCode
    {
        if ($raw === null) {
            return null;
        }
        try {
            return FacilityCode::fromString($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function summaryFrom(PurchaseOrder $order): PurchaseOrderSummaryView
    {
        $lineCount = 0;
        $totalOrderedUnits = 0;
        $totalReceivedUnits = 0;
        $totalCostCents = 0;

        foreach ($order->lines() as $line) {
            $lineCount++;
            $ordered = $line->orderedQuantity()->units;
            $totalOrderedUnits += $ordered;
            $totalReceivedUnits += $line->receivedQuantity()->units;
            $totalCostCents += $ordered * $line->costPerUnit()->cents;
        }

        return new PurchaseOrderSummaryView(
            purchaseOrderId: $order->id()->value,
            vendorId: $order->vendorId()->value,
            facilityCode: $order->facilityCode()->value,
            status: $order->status()->value,
            lineCount: $lineCount,
            totalOrderedUnits: $totalOrderedUnits,
            totalReceivedUnits: $totalReceivedUnits,
            totalCostCents: $totalCostCents,
            createdAt: $order->createdAt(),
            sentAt: $order->sentAt(),
        );
    }
}
