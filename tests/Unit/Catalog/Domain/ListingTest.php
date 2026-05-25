<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\Event\ListingArchived;
use App\Catalog\Domain\Event\ListingFeesUpdated;
use App\Catalog\Domain\Event\ListingLedgerAccountUpdated;
use App\Catalog\Domain\Event\ListingRegistered;
use App\Catalog\Domain\Event\ListingRenamed;
use App\Catalog\Domain\Event\ListingTaxTreatmentUpdated;
use App\Catalog\Domain\Exception\InvalidListingName;
use App\Catalog\Domain\Exception\ListingIsArchived;
use App\Catalog\Domain\Listing;
use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\ValueObject\Currency;
use App\Catalog\Domain\ValueObject\Fee;
use App\Catalog\Domain\ValueObject\LedgerAccount;
use App\Catalog\Domain\ValueObject\ListingCode;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Domain\ValueObject\Money;
use App\Catalog\Domain\ValueObject\TaxTreatment;
use App\Tests\Support\Fake\SequenceListingIdentityGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class ListingTest extends TestCase
{
    private MockClock $clock;
    private SequenceListingIdentityGenerator $ids;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $this->ids = new SequenceListingIdentityGenerator(
            ListingId::fromString('019571bf-5d51-7000-b500-000000000001'),
        );
    }

    #[Test]
    #[TestDox('::register() records ListingRegistered with the full initial state and the clock instant.')]
    public function register_records_listing_registered(): void
    {
        $id = $this->ids->nextListingId();
        $fees = [Fee::of(Money::ofCents(1500, Currency::USD), 'Adult')];

        $listing = Listing::register(
            $id,
            ListingCode::of('YOGA-101'),
            ListingKind::Program,
            'Beginner Yoga',
            $fees,
            TaxTreatment::of(true, false),
            LedgerAccount::of('4000-PRG'),
            $this->clock,
        );

        $events = $listing->releaseEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(ListingRegistered::class, $event);
        self::assertTrue($event->listingId->equals($id));
        self::assertSame('YOGA-101', $event->code->value);
        self::assertSame(ListingKind::Program, $event->kind);
        self::assertSame('Beginner Yoga', $event->name);
        self::assertSame($fees, $event->fees);
        self::assertTrue($event->taxTreatment->equals(TaxTreatment::of(true, false)));
        self::assertSame('4000-PRG', $event->ledgerAccount->value);
        self::assertEquals($this->clock->now(), $event->occurredAt);
    }

    #[Test]
    #[TestDox('::release_events() returns the buffer and clears it.')]
    public function release_events_clears_the_buffer(): void
    {
        $listing = $this->register();

        self::assertCount(1, $listing->releaseEvents());
        self::assertSame([], $listing->releaseEvents());
    }

    #[Test]
    #[TestDox('::register() trims and rejects an empty name with InvalidListingName.')]
    public function register_rejects_empty_name(): void
    {
        $this->expectException(InvalidListingName::class);

        Listing::register(
            $this->ids->nextListingId(),
            ListingCode::of('SKU-1'),
            ListingKind::Inventory,
            '   ',
            [],
            TaxTreatment::none(),
            LedgerAccount::of('4000'),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::register() rejects a name longer than 255 characters with InvalidListingName.')]
    public function register_rejects_overlong_name(): void
    {
        $this->expectException(InvalidListingName::class);

        Listing::register(
            $this->ids->nextListingId(),
            ListingCode::of('SKU-1'),
            ListingKind::Inventory,
            str_repeat('a', 256),
            [],
            TaxTreatment::none(),
            LedgerAccount::of('4000'),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::updateFees() records ListingFeesUpdated and replaces the fee list.')]
    public function update_fees_records_event_and_updates_state(): void
    {
        $listing = $this->register();
        $listing->releaseEvents();

        $newFees = [
            Fee::of(Money::ofCents(2000, Currency::USD), 'Adult'),
            Fee::of(Money::ofCents(1000, Currency::USD), 'Child'),
        ];

        $this->clock->modify('+1 hour');
        $listing->updateFees($newFees, $this->clock);

        $events = $listing->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ListingFeesUpdated::class, $events[0]);
        self::assertSame($newFees, $events[0]->fees);
        self::assertEquals($this->clock->now(), $events[0]->occurredAt);
        self::assertSame($newFees, $listing->fees());
    }

    #[Test]
    #[TestDox('::updateFees() is a no-op when the new fee list is value-equal to the current one.')]
    public function update_fees_is_idempotent_when_equal(): void
    {
        $fees = [Fee::of(Money::ofCents(1500, Currency::USD), 'Adult')];
        $listing = $this->register($fees);
        $listing->releaseEvents();

        $listing->updateFees(
            [Fee::of(Money::ofCents(1500, Currency::USD), 'Adult')],
            $this->clock,
        );

        self::assertSame([], $listing->releaseEvents());
    }

    #[Test]
    #[TestDox('::updateTaxTreatment() records ListingTaxTreatmentUpdated and updates state.')]
    public function update_tax_treatment_records_event(): void
    {
        $listing = $this->register();
        $listing->releaseEvents();

        $next = TaxTreatment::of(true, true);
        $listing->updateTaxTreatment($next, $this->clock);

        $events = $listing->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ListingTaxTreatmentUpdated::class, $events[0]);
        self::assertTrue($events[0]->taxTreatment->equals($next));
        self::assertTrue($listing->taxTreatment()->equals($next));
    }

    #[Test]
    #[TestDox('::updateTaxTreatment() is a no-op when the value is equal to the current one.')]
    public function update_tax_treatment_is_idempotent(): void
    {
        $listing = $this->register();
        $listing->releaseEvents();

        $listing->updateTaxTreatment(TaxTreatment::of(true, false), $this->clock);

        self::assertSame([], $listing->releaseEvents());
    }

    #[Test]
    #[TestDox('::updateLedgerAccount() records ListingLedgerAccountUpdated and updates state.')]
    public function update_ledger_account_records_event(): void
    {
        $listing = $this->register();
        $listing->releaseEvents();

        $next = LedgerAccount::of('4100-PRG');
        $listing->updateLedgerAccount($next, $this->clock);

        $events = $listing->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ListingLedgerAccountUpdated::class, $events[0]);
        self::assertTrue($events[0]->ledgerAccount->equals($next));
        self::assertTrue($listing->ledgerAccount()->equals($next));
    }

    #[Test]
    #[TestDox('::updateLedgerAccount() is a no-op when the value is equal to the current one.')]
    public function update_ledger_account_is_idempotent(): void
    {
        $listing = $this->register();
        $listing->releaseEvents();

        $listing->updateLedgerAccount(LedgerAccount::of('4000-PRG'), $this->clock);

        self::assertSame([], $listing->releaseEvents());
    }

    #[Test]
    #[TestDox('::rename() records ListingRenamed when the trimmed name changes.')]
    public function rename_records_event(): void
    {
        $listing = $this->register();
        $listing->releaseEvents();

        $listing->rename('  Intermediate Yoga  ', $this->clock);

        $events = $listing->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ListingRenamed::class, $events[0]);
        self::assertSame('Intermediate Yoga', $events[0]->name);
        self::assertSame('Intermediate Yoga', $listing->name());
    }

    #[Test]
    #[TestDox('::rename() is a no-op when the trimmed name equals the current name.')]
    public function rename_is_idempotent(): void
    {
        $listing = $this->register();
        $listing->releaseEvents();

        $listing->rename(' Beginner Yoga ', $this->clock);

        self::assertSame([], $listing->releaseEvents());
    }

    #[Test]
    #[TestDox('::rename() rejects an empty name with InvalidListingName.')]
    public function rename_rejects_empty(): void
    {
        $listing = $this->register();
        $listing->releaseEvents();

        $this->expectException(InvalidListingName::class);

        $listing->rename('   ', $this->clock);
    }

    #[Test]
    #[TestDox('::rename() rejects a name longer than 255 characters with InvalidListingName.')]
    public function rename_rejects_overlong_name(): void
    {
        $listing = $this->register();
        $listing->releaseEvents();

        $this->expectException(InvalidListingName::class);

        $listing->rename(str_repeat('a', 256), $this->clock);
    }

    #[Test]
    #[TestDox('::archive() records ListingArchived and flips the archived flag.')]
    public function archive_records_event(): void
    {
        $listing = $this->register();
        $listing->releaseEvents();

        $listing->archive($this->clock);

        $events = $listing->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ListingArchived::class, $events[0]);
        self::assertTrue($listing->isArchived());
    }

    #[Test]
    #[TestDox('::archive() is idempotent: a second call records nothing and leaves the listing archived.')]
    public function archive_is_idempotent(): void
    {
        $listing = $this->register();
        $listing->releaseEvents();

        $listing->archive($this->clock);
        $listing->releaseEvents();
        $listing->archive($this->clock);

        self::assertSame([], $listing->releaseEvents());
        self::assertTrue($listing->isArchived());
    }

    #[Test]
    #[TestDox('Any mutator on an archived listing throws ListingIsArchived: updateFees.')]
    public function update_fees_after_archive_throws(): void
    {
        $listing = $this->register();
        $listing->archive($this->clock);
        $listing->releaseEvents();

        $this->expectException(ListingIsArchived::class);

        $listing->updateFees([], $this->clock);
    }

    #[Test]
    #[TestDox('Any mutator on an archived listing throws ListingIsArchived: updateTaxTreatment.')]
    public function update_tax_after_archive_throws(): void
    {
        $listing = $this->register();
        $listing->archive($this->clock);
        $listing->releaseEvents();

        $this->expectException(ListingIsArchived::class);

        $listing->updateTaxTreatment(TaxTreatment::none(), $this->clock);
    }

    #[Test]
    #[TestDox('Any mutator on an archived listing throws ListingIsArchived: updateLedgerAccount.')]
    public function update_ledger_after_archive_throws(): void
    {
        $listing = $this->register();
        $listing->archive($this->clock);
        $listing->releaseEvents();

        $this->expectException(ListingIsArchived::class);

        $listing->updateLedgerAccount(LedgerAccount::of('9999'), $this->clock);
    }

    #[Test]
    #[TestDox('Any mutator on an archived listing throws ListingIsArchived: rename.')]
    public function rename_after_archive_throws(): void
    {
        $listing = $this->register();
        $listing->archive($this->clock);
        $listing->releaseEvents();

        $this->expectException(ListingIsArchived::class);

        $listing->rename('Something Else', $this->clock);
    }

    /**
     * @param list<Fee>|null $fees
     */
    private function register(?array $fees = null): Listing
    {
        return Listing::register(
            ListingId::fromString('019571bf-5d51-7000-b500-000000000002'),
            ListingCode::of('YOGA-101'),
            ListingKind::Program,
            'Beginner Yoga',
            $fees ?? [Fee::of(Money::ofCents(1500, Currency::USD), 'Adult')],
            TaxTreatment::of(true, false),
            LedgerAccount::of('4000-PRG'),
            $this->clock,
        );
    }
}
