<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\RegisterListing;
use App\Catalog\Application\Command\RegisterListingHandler;
use App\Catalog\Domain\Event\ListingRegistered;
use App\Catalog\Domain\Exception\DuplicateListingCode;
use App\Catalog\Domain\ValueObject\ListingCode;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Infrastructure\Persistence\InMemory\InMemoryListings;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Fake\SequenceListingIdentityGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[Small]
final class RegisterListingHandlerTest extends TestCase
{
    private InMemoryListings $listings;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;
    private SequenceListingIdentityGenerator $ids;
    private RegisterListingHandler $handler;

    protected function setUp(): void
    {
        $this->listings = new InMemoryListings();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-25 12:00:00'));
        $this->ids = new SequenceListingIdentityGenerator(
            ListingId::fromString('019571bf-5d51-7000-b500-000000000101'),
        );
        $this->handler = new RegisterListingHandler(
            $this->listings,
            $this->ids,
            $this->clock,
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox(
        'Persists the new listing, returns its id, and dispatches ListingRegistered '
        . 'with the DispatchAfterCurrentBusStamp.'
    )]
    public function happy_path_persists_and_dispatches(): void
    {
        $id = ($this->handler)(new RegisterListing(
            code: 'yoga-101',
            kind: 'PROGRAM',
            name: 'Beginner Yoga',
            fees: [['amountCents' => 1500, 'currency' => 'USD', 'label' => 'Adult']],
            taxApply: true,
            taxIncludedInFee: false,
            ledgerAccount: '4000-prg',
        ));

        self::assertSame('019571bf-5d51-7000-b500-000000000101', $id->value);

        $loaded = $this->listings->byCode(ListingCode::of('YOGA-101'));
        self::assertSame('PROGRAM', $loaded->kind()->value);
        self::assertSame('Beginner Yoga', $loaded->name());

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(ListingRegistered::class, $messages[0]);

        $envelopes = $this->eventBus->envelopes();
        self::assertNotNull($envelopes[0]->last(DispatchAfterCurrentBusStamp::class));
    }

    #[Test]
    #[TestDox('Throws DuplicateListingCode and dispatches nothing when a listing with that code already exists.')]
    public function duplicate_code_rejected(): void
    {
        ($this->handler)($this->validCommand());

        $this->eventBus = new RecordingMessageBus();
        $this->ids = new SequenceListingIdentityGenerator(
            ListingId::fromString('019571bf-5d51-7000-b500-000000000102'),
        );
        $secondHandler = new RegisterListingHandler(
            $this->listings,
            $this->ids,
            $this->clock,
            $this->eventBus,
        );

        $this->expectException(DuplicateListingCode::class);

        try {
            ($secondHandler)($this->validCommand());
        } finally {
            self::assertSame([], $this->eventBus->dispatchedMessages());
        }
    }

    private function validCommand(): RegisterListing
    {
        return new RegisterListing(
            code: 'YOGA-101',
            kind: 'PROGRAM',
            name: 'Beginner Yoga',
            fees: [['amountCents' => 1500, 'currency' => 'USD', 'label' => 'Adult']],
            taxApply: true,
            taxIncludedInFee: false,
            ledgerAccount: '4000-PRG',
        );
    }
}
