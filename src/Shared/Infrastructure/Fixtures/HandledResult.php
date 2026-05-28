<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Fixtures;

use LogicException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Extracts the typed result of a synchronous Messenger command-bus
 * handler from its envelope.
 *
 * Fixture loaders dispatch commands like
 * {@see \App\Inventory\Application\Command\RegisterVendor} and need the
 * returned identity to chain follow-up commands; this helper avoids
 * duplicating the HandledStamp lookup + assertion logic across every
 * fixture class. The helper is fixture-/dev-/test-tier infrastructure
 * and is intentionally kept under Shared/Infrastructure/ so both
 * Inventory and Catalog fixtures (and any future bounded-context
 * fixture set) can consume it without crossing context boundaries.
 */
final class HandledResult
{
    /**
     * @template T of object
     *
     * @param class-string<T> $expectedClass
     *
     * @return T
     */
    public static function from(Envelope $envelope, string $expectedClass): object
    {
        $stamp = $envelope->last(HandledStamp::class);
        if (!$stamp instanceof HandledStamp) {
            throw new LogicException(sprintf(
                'Envelope has no HandledStamp; expected handler to return %s.',
                $expectedClass,
            ));
        }

        $result = $stamp->getResult();
        if (!$result instanceof $expectedClass) {
            throw new LogicException(sprintf(
                'Handler returned %s, expected %s.',
                get_debug_type($result),
                $expectedClass,
            ));
        }

        return $result;
    }
}
