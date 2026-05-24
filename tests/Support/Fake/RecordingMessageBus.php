<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * In-memory MessageBus that records every dispatched message + stamps so
 * tests can assert what an application service published without booting
 * the real Messenger transport.
 *
 * The bus never invokes handlers; it is purely a sink. Application
 * services use it to publish post-commit domain events; the SUT
 * orchestration is what the tests want to pin down.
 */
final class RecordingMessageBus implements MessageBusInterface
{
    /** @var list<Envelope> */
    private array $envelopes = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = $message instanceof Envelope
            ? $message->with(...$stamps)
            : new Envelope($message, $stamps);

        $this->envelopes[] = $envelope;

        return $envelope;
    }

    /**
     * @return list<object> The raw messages in dispatch order.
     */
    public function dispatchedMessages(): array
    {
        return array_map(
            static fn (Envelope $envelope): object => $envelope->getMessage(),
            $this->envelopes,
        );
    }

    /**
     * @return list<Envelope>
     */
    public function envelopes(): array
    {
        return $this->envelopes;
    }
}
