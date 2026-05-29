<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Shared helper for Households HTTP controllers that dispatch commands through
 * the command bus. Messenger wraps handler exceptions in
 * `HandlerFailedException`; unwrap once so the controller's catch blocks see
 * the original domain exception type.
 *
 * Consumers must hold the command bus on a property named `$commandBus`.
 * Mirrors the Inventory context's trait of the same name — each bounded
 * context keeps its own copy so the dependency never crosses the boundary.
 */
trait DispatchesCommandsUnwrapping
{
    private function dispatchCommandUnwrapping(object $command): void
    {
        /** @var MessageBusInterface $bus */
        $bus = $this->commandBus;

        try {
            $bus->dispatch($command);
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }
    }
}
