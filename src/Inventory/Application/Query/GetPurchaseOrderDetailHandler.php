<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

use App\Inventory\Application\Query\View\PurchaseOrderDetailView;
use App\Inventory\Application\Query\View\PurchaseOrderLineDetailView;
use App\Inventory\Domain\PurchaseOrder;
use App\Inventory\Domain\PurchaseOrders;
use App\Inventory\Domain\PurchaseOrderStatus;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use DateTimeInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Read-side handler for the LRA-90 Purchase Order detail page.
 *
 * Projects the aggregate's lines into flat
 * {@see PurchaseOrderLineDetailView} rows with pre-computed
 * `remainingUnits` and `isFullyReceived`. Derives the set of
 * lifecycle actions still available from the current status — the
 * controller uses this list as its defence-in-depth gate against
 * stale-button submissions.
 */
#[AsMessageHandler(bus: 'query.bus')]
final class GetPurchaseOrderDetailHandler
{
    public const string TRANSITION_SEND = 'send';

    public const string TRANSITION_RECEIVE_LINE = 'receiveLine';

    public const string TRANSITION_VERIFY = 'verify';

    public function __construct(
        private readonly PurchaseOrders $purchaseOrders,
    ) {
    }

    public function __invoke(GetPurchaseOrderDetail $query): PurchaseOrderDetailView
    {
        $order = $this->purchaseOrders->byId(PurchaseOrderId::fromString($query->purchaseOrderId));

        $lines = [];
        foreach ($order->lines() as $line) {
            $ordered = $line->orderedQuantity()->units;
            $received = $line->receivedQuantity()->units;
            $lines[] = new PurchaseOrderLineDetailView(
                lineId: $line->id()->value,
                itemId: $line->itemId()->value,
                orderedUnits: $ordered,
                receivedUnits: $received,
                remainingUnits: $ordered - $received,
                costPerUnitCents: $line->costPerUnit()->cents,
                isFullyReceived: $line->isFullyReceived(),
            );
        }

        return new PurchaseOrderDetailView(
            purchaseOrderId: $order->id()->value,
            vendorId: $order->vendorId()->value,
            facilityCode: $order->facilityCode()->value,
            status: $order->status()->value,
            sentAtIso: self::isoOrNull($order->sentAt()),
            estimatedArrivalIso: self::isoOrNull($order->estimatedArrival()),
            verifiedAtIso: self::isoOrNull($order->verifiedAt()),
            verifiedByUserId: $order->verifiedByUserId(),
            createdAtIso: $order->createdAt()->format(DateTimeInterface::ATOM),
            updatedAtIso: $order->updatedAt()->format(DateTimeInterface::ATOM),
            lines: $lines,
            allowedTransitions: self::transitionsFor($order),
            isLineEditable: $order->status() === PurchaseOrderStatus::Draft,
        );
    }

    /**
     * @return list<string>
     */
    private static function transitionsFor(PurchaseOrder $order): array
    {
        return match ($order->status()) {
            PurchaseOrderStatus::Draft => [self::TRANSITION_SEND],
            PurchaseOrderStatus::Sent,
            PurchaseOrderStatus::PartiallyReceived => [self::TRANSITION_RECEIVE_LINE],
            PurchaseOrderStatus::FullyReceived => [self::TRANSITION_VERIFY],
            PurchaseOrderStatus::Verified,
            PurchaseOrderStatus::Cancelled => [],
        };
    }

    private static function isoOrNull(?\DateTimeImmutable $at): ?string
    {
        return $at?->format(DateTimeInterface::ATOM);
    }
}
