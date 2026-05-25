<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Integration;

use App\Catalog\Integration\Event\LineSold;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

/**
 * Pins down the post-commit dispatch guarantee Catalog publishes as part
 * of the LineSold integration contract. The producer (the future
 * Transactions context) is expected to dispatch LineSold via the
 * event.bus with DispatchAfterCurrentBusStamp; combined with the
 * command.bus doctrine_transaction middleware, the envelope reaches the
 * async transport only after the writing transaction commits. When the
 * surrounding transaction rolls back, the envelope MUST NOT be
 * published.
 */
#[Medium]
final class LineSoldPostCommitTest extends KernelTestCase
{
    private const string LISTING_ID = '019571bf-5d51-7000-b500-000000000301';

    #[Test]
    #[TestDox('LineSold lands on the async transport when dispatched via the event bus.')]
    public function lineSold_is_routed_to_async_transport(): void
    {
        self::bootKernel();
        $eventBus = self::getEventBus();
        $transport = self::getAsyncTransport();

        $eventBus->dispatch($this->sampleEvent(), [new DispatchAfterCurrentBusStamp()]);

        $envelopes = $transport->getSent();
        self::assertCount(1, $envelopes);
        self::assertInstanceOf(LineSold::class, $envelopes[0]->getMessage());
    }

    #[Test]
    #[TestDox(
        'LineSold dispatched inside a command-bus handler that throws after dispatch '
        . 'is NOT published, proving the post-commit semantics promised by the contract.'
    )]
    public function rollback_suppresses_lineSold_publication(): void
    {
        self::bootKernel();
        $commandBus = self::getCommandBus();
        $transport = self::getAsyncTransport();

        $rootCause = null;
        try {
            $commandBus->dispatch(new FailingPublishCommand($this->sampleEvent()));
            self::fail('FailingPublishHandler must throw to drive the rollback path.');
        } catch (HandlerFailedException $wrapped) {
            $rootCause = self::unwrap($wrapped);
        }

        self::assertInstanceOf(RuntimeException::class, $rootCause);
        self::assertSame('rollback', $rootCause->getMessage());

        self::assertSame(
            [],
            $transport->getSent(),
            'LineSold must NOT reach the async transport when the writing command rolls back.'
        );
    }

    private static function getCommandBus(): MessageBusInterface
    {
        $bus = self::getContainer()->get('command.bus');
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        return $bus;
    }

    private static function getEventBus(): MessageBusInterface
    {
        $bus = self::getContainer()->get('event.bus');
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        return $bus;
    }

    private static function getAsyncTransport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(TransportInterface::class, $transport);
        self::assertInstanceOf(
            InMemoryTransport::class,
            $transport,
            'Test env must route async to in-memory:// — see config/packages/messenger.yaml when@test.',
        );
        $transport->reset();

        return $transport;
    }

    private static function unwrap(Throwable $thrown): Throwable
    {
        $current = $thrown;
        while ($current->getPrevious() instanceof Throwable) {
            $current = $current->getPrevious();
        }

        return $current;
    }

    private function sampleEvent(): LineSold
    {
        return new LineSold(
            listingId: self::LISTING_ID,
            listingKind: 'PROGRAM',
            listingCode: 'YOGA-101',
            quantity: 1,
            facilityCode: 'MAIN',
            transactionId: 'TXN-0001',
            occurredAt: new DateTimeImmutable('2026-05-25 14:00:00'),
        );
    }
}
