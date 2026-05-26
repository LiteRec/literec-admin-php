<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\PurchaseOrders;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class MarkPurchaseOrderSentHandler
{
    public function __construct(
        private readonly PurchaseOrders $purchaseOrders,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(MarkPurchaseOrderSent $command): void
    {
        $order = $this->purchaseOrders->byId(PurchaseOrderId::fromString($command->purchaseOrderId));

        $order->send(
            new DateTimeImmutable($command->sentAtIso),
            $command->estimatedArrivalIso !== null
                ? new DateTimeImmutable($command->estimatedArrivalIso)
                : null,
            $this->clock,
        );

        $this->purchaseOrders->save($order);

        foreach ($order->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
