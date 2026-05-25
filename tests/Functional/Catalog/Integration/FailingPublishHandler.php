<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Integration;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class FailingPublishHandler
{
    public function __construct(
        #[Autowire(service: 'event.bus')]
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(FailingPublishCommand $command): void
    {
        $this->eventBus->dispatch($command->event, [new DispatchAfterCurrentBusStamp()]);

        throw new RuntimeException('rollback');
    }
}
