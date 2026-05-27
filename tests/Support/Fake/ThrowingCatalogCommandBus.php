<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * MessageBus double that throws the configured error on every
 * dispatch. Used by the LRA-98 cross-bus rollback test to simulate
 * a Catalog-side failure (duplicate listing code) and verify that
 * the Inventory write never happens.
 */
final readonly class ThrowingCatalogCommandBus implements MessageBusInterface
{
    public function __construct(private Throwable $error)
    {
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        throw $this->error;
    }
}
