<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\ArchiveListing;
use App\Catalog\Application\Command\ArchiveListingHandler;
use App\Catalog\Application\Command\RegisterListing;
use App\Catalog\Application\Command\RegisterListingHandler;
use App\Catalog\Application\Command\RenameListing;
use App\Catalog\Application\Command\RenameListingHandler;
use App\Catalog\Application\Command\UpdateListingFees;
use App\Catalog\Application\Command\UpdateListingFeesHandler;
use App\Catalog\Application\Command\UpdateListingLedgerAccount;
use App\Catalog\Application\Command\UpdateListingLedgerAccountHandler;
use App\Catalog\Application\Command\UpdateListingTaxTreatment;
use App\Catalog\Application\Command\UpdateListingTaxTreatmentHandler;
use App\Catalog\Domain\Event\ListingArchived;
use App\Catalog\Domain\Event\ListingFeesUpdated;
use App\Catalog\Domain\Event\ListingLedgerAccountUpdated;
use App\Catalog\Domain\Event\ListingRenamed;
use App\Catalog\Domain\Event\ListingTaxTreatmentUpdated;
use App\Catalog\Domain\Exception\ListingNotFound;
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
final class UpdateListingHandlersTest extends TestCase
{
    private const string LISTING_ID = '019571bf-5d51-7000-b500-000000000201';

    private InMemoryListings $listings;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->listings = new InMemoryListings();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-25 12:00:00'));

        $register = new RegisterListingHandler(
            $this->listings,
            new SequenceListingIdentityGenerator(ListingId::fromString(self::LISTING_ID)),
            $this->clock,
            $this->eventBus,
        );
        ($register)(new RegisterListing(
            code: 'YOGA-101',
            kind: 'PROGRAM',
            name: 'Beginner Yoga',
            fees: [['amountCents' => 1500, 'currency' => 'USD', 'label' => 'Adult']],
            taxApply: true,
            taxIncludedInFee: false,
            ledgerAccount: '4000-PRG',
        ));
        // Drop the registration event so the assertions below see only the update event.
        $this->eventBus = new RecordingMessageBus();
    }

    #[Test]
    #[TestDox('UpdateListingFees replaces the fees and dispatches ListingFeesUpdated.')]
    public function update_fees_dispatches_event(): void
    {
        $handler = new UpdateListingFeesHandler($this->listings, $this->clock, $this->eventBus);
        $this->clock->modify('+1 hour');

        ($handler)(new UpdateListingFees(self::LISTING_ID, [
            ['amountCents' => 2000, 'currency' => 'USD', 'label' => 'Adult'],
            ['amountCents' => 1000, 'currency' => 'USD', 'label' => 'Child'],
        ]));

        $events = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $events);
        self::assertInstanceOf(ListingFeesUpdated::class, $events[0]);
        self::assertCount(2, $this->listings->byId(ListingId::fromString(self::LISTING_ID))->fees());
        self::assertNotNull(
            $this->eventBus->envelopes()[0]->last(DispatchAfterCurrentBusStamp::class)
        );
    }

    #[Test]
    #[TestDox('UpdateListingTaxTreatment updates state and dispatches ListingTaxTreatmentUpdated.')]
    public function update_tax_dispatches_event(): void
    {
        $handler = new UpdateListingTaxTreatmentHandler($this->listings, $this->clock, $this->eventBus);

        ($handler)(new UpdateListingTaxTreatment(self::LISTING_ID, true, true));

        $events = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $events);
        self::assertInstanceOf(ListingTaxTreatmentUpdated::class, $events[0]);
    }

    #[Test]
    #[TestDox('UpdateListingLedgerAccount updates state and dispatches ListingLedgerAccountUpdated.')]
    public function update_ledger_dispatches_event(): void
    {
        $handler = new UpdateListingLedgerAccountHandler($this->listings, $this->clock, $this->eventBus);

        ($handler)(new UpdateListingLedgerAccount(self::LISTING_ID, '4100-PRG'));

        $events = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $events);
        self::assertInstanceOf(ListingLedgerAccountUpdated::class, $events[0]);
    }

    #[Test]
    #[TestDox('RenameListing updates the name and dispatches ListingRenamed.')]
    public function rename_dispatches_event(): void
    {
        $handler = new RenameListingHandler($this->listings, $this->clock, $this->eventBus);

        ($handler)(new RenameListing(self::LISTING_ID, 'Intermediate Yoga'));

        $events = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $events);
        self::assertInstanceOf(ListingRenamed::class, $events[0]);
        self::assertSame(
            'Intermediate Yoga',
            $this->listings->byId(ListingId::fromString(self::LISTING_ID))->name(),
        );
    }

    #[Test]
    #[TestDox('ArchiveListing flips the archived flag and dispatches ListingArchived.')]
    public function archive_dispatches_event(): void
    {
        $handler = new ArchiveListingHandler($this->listings, $this->clock, $this->eventBus);

        ($handler)(new ArchiveListing(self::LISTING_ID));

        $events = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $events);
        self::assertInstanceOf(ListingArchived::class, $events[0]);
        self::assertTrue($this->listings->byId(ListingId::fromString(self::LISTING_ID))->isArchived());
    }

    #[Test]
    #[TestDox(
        'RenameListingHandler bubbles ListingNotFound when the id is unknown — '
        . 'the lookup happens identically via Listings::byId() in every update handler.'
    )]
    public function unknown_id_bubbles_listing_not_found(): void
    {
        $unknown = '019571bf-5d51-7000-b500-000000000999';
        $handler = new RenameListingHandler($this->listings, $this->clock, $this->eventBus);

        $this->expectException(ListingNotFound::class);

        ($handler)(new RenameListing($unknown, 'Anything'));
    }
}
