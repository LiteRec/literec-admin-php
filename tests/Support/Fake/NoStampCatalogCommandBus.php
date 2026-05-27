<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * MessageBus double that returns an envelope WITHOUT a HandledStamp.
 * Lets the LRA-98 cross-bus handler test prove its fail-loud path
 * when the inner Catalog dispatch yields no result — the
 * RegisterInventoryItemHandler must surface
 * CrossBusRegistrationFailed rather than silently proceed with a
 * null listing id.
 */
final class NoStampCatalogCommandBus implements MessageBusInterface
{
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        return new Envelope($message);
    }
}
