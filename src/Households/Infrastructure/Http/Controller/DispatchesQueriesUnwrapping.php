<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use LogicException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Throwable;

/**
 * Shared helper for Households HTTP controllers that dispatch a read query
 * through the query bus, unwrap Messenger's `HandlerFailedException`, and
 * assert the projected result type.
 *
 * Consumers must hold the query bus on a property named `$queryBus`. Mirrors
 * the Inventory context's trait of the same name — each bounded context keeps
 * its own copy so the dependency never crosses the boundary.
 */
trait DispatchesQueriesUnwrapping
{
    /**
     * @template T of object
     *
     * @param class-string<T> $expectedClass
     *
     * @return T
     */
    private function dispatchQueryUnwrapping(object $query, string $expectedClass): object
    {
        /** @var MessageBusInterface $bus */
        $bus = $this->queryBus;

        try {
            $envelope = $bus->dispatch($query);
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }

        $stamp = $envelope->last(HandledStamp::class);
        if ($stamp === null) {
            throw new LogicException(sprintf(
                '%s dispatch returned no HandledStamp.',
                $query::class,
            ));
        }

        $result = $stamp->getResult();
        if (! $result instanceof $expectedClass) {
            throw new LogicException(sprintf(
                '%s handler returned %s, expected %s.',
                $query::class,
                get_debug_type($result),
                $expectedClass,
            ));
        }

        return $result;
    }
}
