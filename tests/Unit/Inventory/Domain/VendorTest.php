<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Inventory\Domain\Event\VendorAddressUpdated;
use App\Inventory\Domain\Event\VendorArchived;
use App\Inventory\Domain\Event\VendorContactUpdated;
use App\Inventory\Domain\Event\VendorEmailUpdated;
use App\Inventory\Domain\Event\VendorPhoneUpdated;
use App\Inventory\Domain\Event\VendorRegistered;
use App\Inventory\Domain\Event\VendorRenamed;
use App\Inventory\Domain\Exception\VendorIsArchived;
use App\Inventory\Domain\Vendor;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Inventory\Domain\ValueObject\VendorAddress;
use App\Inventory\Domain\ValueObject\VendorCode;
use App\Inventory\Domain\ValueObject\VendorContact;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Domain\ValueObject\VendorName;
use Closure;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

#[Small]
final class VendorTest extends TestCase
{
    private const ID = '019571bf-5d51-7000-b500-000000000020';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-25 12:00:00'));
    }

    #[Test]
    #[TestDox('::register() records VendorRegistered with the full initial state and the clock instant.')]
    public function register_records_vendor_registered(): void
    {
        $vendor = $this->register();

        $events = $vendor->releaseEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(VendorRegistered::class, $event);
        self::assertTrue($event->vendorId->equals(VendorId::fromString(self::ID)));
        self::assertSame('ACME', $event->code->value);
        self::assertSame('Acme Supply Co.', $event->name->value);
        self::assertSame('Jane Smith', $event->contact->value);
        self::assertNotNull($event->email);
        self::assertSame('jane@acme.test', $event->email->value);
        self::assertNotNull($event->phone);
        self::assertSame('+15551234567', $event->phone->value);
        self::assertNotNull($event->address);
        self::assertSame('123 Main St', $event->address->street);
        self::assertEquals($this->clock->now(), $event->occurredAt);
    }

    #[Test]
    #[TestDox('::releaseEvents() returns the buffer and clears it.')]
    public function release_events_clears_the_buffer(): void
    {
        $vendor = $this->register();

        self::assertCount(1, $vendor->releaseEvents());
        self::assertSame([], $vendor->releaseEvents());
    }

    #[Test]
    #[TestDox('::rename() records VendorRenamed when the name changes.')]
    public function rename_records_event(): void
    {
        $vendor = $this->register();
        $vendor->releaseEvents();

        $this->clock->modify('+1 hour');
        $vendor->rename(VendorName::of('New Name'), $this->clock);

        $events = $vendor->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(VendorRenamed::class, $events[0]);
        self::assertSame('New Name', $events[0]->name->value);
        self::assertSame('New Name', $vendor->name()->value);
        self::assertEquals($this->clock->now(), $vendor->updatedAt());
    }

    #[Test]
    #[TestDox('::rename() is a no-op when the new name equals the current one.')]
    public function rename_is_idempotent(): void
    {
        $vendor = $this->register();
        $vendor->releaseEvents();

        $vendor->rename(VendorName::of('Acme Supply Co.'), $this->clock);

        self::assertSame([], $vendor->releaseEvents());
    }

    #[Test]
    #[TestDox('::updateContact() records VendorContactUpdated when the contact changes.')]
    public function update_contact_records_event(): void
    {
        $vendor = $this->register();
        $vendor->releaseEvents();

        $vendor->updateContact(VendorContact::of('John Doe'), $this->clock);

        $events = $vendor->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(VendorContactUpdated::class, $events[0]);
        self::assertSame('John Doe', $events[0]->contact->value);
        self::assertSame('John Doe', $vendor->contact()->value);
    }

    #[Test]
    #[TestDox('::updateContact() is a no-op when the value equals the current contact.')]
    public function update_contact_is_idempotent(): void
    {
        $vendor = $this->register();
        $vendor->releaseEvents();

        $vendor->updateContact(VendorContact::of('Jane Smith'), $this->clock);

        self::assertSame([], $vendor->releaseEvents());
    }

    #[Test]
    #[TestDox('::updateEmail() records VendorEmailUpdated when the email changes (including to null).')]
    public function update_email_records_event(): void
    {
        $vendor = $this->register();
        $vendor->releaseEvents();

        $vendor->updateEmail(EmailAddress::of('new@acme.test'), $this->clock);

        $events = $vendor->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(VendorEmailUpdated::class, $events[0]);
        self::assertNotNull($events[0]->email);
        self::assertSame('new@acme.test', $events[0]->email->value);

        $vendor->updateEmail(null, $this->clock);

        $events = $vendor->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(VendorEmailUpdated::class, $events[0]);
        self::assertNull($events[0]->email);
        self::assertNull($vendor->email());
    }

    #[Test]
    #[TestDox('::updateEmail() is a no-op when the new value equals the current email.')]
    public function update_email_is_idempotent(): void
    {
        $vendor = $this->register();
        $vendor->releaseEvents();

        $vendor->updateEmail(EmailAddress::of('jane@acme.test'), $this->clock);

        self::assertSame([], $vendor->releaseEvents());
    }

    #[Test]
    #[TestDox('::updateEmail() is a no-op when both current and new values are null.')]
    public function update_email_null_to_null_is_noop(): void
    {
        $vendor = Vendor::register(
            VendorId::fromString(self::ID),
            VendorCode::fromString('ACME'),
            VendorName::of('Acme Supply Co.'),
            VendorContact::of('Jane Smith'),
            null,
            null,
            null,
            $this->clock,
        );
        $vendor->releaseEvents();

        $vendor->updateEmail(null, $this->clock);

        self::assertSame([], $vendor->releaseEvents());
    }

    #[Test]
    #[TestDox('::updatePhone() records VendorPhoneUpdated when the phone changes.')]
    public function update_phone_records_event(): void
    {
        $vendor = $this->register();
        $vendor->releaseEvents();

        $vendor->updatePhone(PhoneNumber::of('+15559876543'), $this->clock);

        $events = $vendor->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(VendorPhoneUpdated::class, $events[0]);
        self::assertNotNull($events[0]->phone);
        self::assertSame('+15559876543', $events[0]->phone->value);
    }

    #[Test]
    #[TestDox('::updatePhone() is a no-op when the new value equals the current phone.')]
    public function update_phone_is_idempotent(): void
    {
        $vendor = $this->register();
        $vendor->releaseEvents();

        $vendor->updatePhone(PhoneNumber::of('+15551234567'), $this->clock);

        self::assertSame([], $vendor->releaseEvents());
    }

    #[Test]
    #[TestDox('::updateAddress() records VendorAddressUpdated when the address changes.')]
    public function update_address_records_event(): void
    {
        $vendor = $this->register();
        $vendor->releaseEvents();

        $next = VendorAddress::of('999 Broadway', null, 'New York', 'NY', '10001', 'US');
        $vendor->updateAddress($next, $this->clock);

        $events = $vendor->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(VendorAddressUpdated::class, $events[0]);
        self::assertNotNull($events[0]->address);
        self::assertSame('999 Broadway', $events[0]->address->street);
        self::assertNotNull($vendor->address());
        self::assertSame('999 Broadway', $vendor->address()->street);
    }

    #[Test]
    #[TestDox('::updateAddress() is a no-op when the new address value-equals the current one.')]
    public function update_address_is_idempotent(): void
    {
        $vendor = $this->register();
        $vendor->releaseEvents();

        $vendor->updateAddress(
            VendorAddress::of('123 Main St', null, 'Springfield', 'IL', '62701', 'US'),
            $this->clock,
        );

        self::assertSame([], $vendor->releaseEvents());
    }

    #[Test]
    #[TestDox('::archive() records VendorArchived and flips the archived flag.')]
    public function archive_records_event(): void
    {
        $vendor = $this->register();
        $vendor->releaseEvents();

        $vendor->archive($this->clock);

        $events = $vendor->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(VendorArchived::class, $events[0]);
        self::assertTrue($vendor->isArchived());
    }

    #[Test]
    #[TestDox('::archive() is idempotent: a second call records nothing and leaves the vendor archived.')]
    public function archive_is_idempotent(): void
    {
        $vendor = $this->register();
        $vendor->releaseEvents();

        $vendor->archive($this->clock);
        $vendor->releaseEvents();
        $vendor->archive($this->clock);

        self::assertSame([], $vendor->releaseEvents());
        self::assertTrue($vendor->isArchived());
    }

    /**
     * @return Generator<string, array{Closure(Vendor, ClockInterface): void}>
     */
    public static function mutators(): Generator
    {
        yield 'rename' => [
            static fn(Vendor $v, ClockInterface $c) => $v->rename(VendorName::of('Other'), $c),
        ];
        yield 'updateContact' => [
            static fn(Vendor $v, ClockInterface $c) => $v->updateContact(VendorContact::of('Other'), $c),
        ];
        yield 'updateEmail' => [
            static fn(Vendor $v, ClockInterface $c) => $v->updateEmail(EmailAddress::of('other@x.test'), $c),
        ];
        yield 'updatePhone' => [
            static fn(Vendor $v, ClockInterface $c) => $v->updatePhone(PhoneNumber::of('5550000000'), $c),
        ];
        yield 'updateAddress' => [
            static fn(Vendor $v, ClockInterface $c) => $v->updateAddress(null, $c),
        ];
    }

    /**
     * @param Closure(Vendor, ClockInterface): void $mutator
     */
    #[Test]
    #[DataProvider('mutators')]
    #[TestDox('Any mutator on an archived vendor throws VendorIsArchived: $_dataName.')]
    public function mutator_after_archive_throws(Closure $mutator): void
    {
        $vendor = $this->register();
        $vendor->archive($this->clock);
        $vendor->releaseEvents();

        $this->expectException(VendorIsArchived::class);

        $mutator($vendor, $this->clock);
    }

    private function register(): Vendor
    {
        return Vendor::register(
            VendorId::fromString(self::ID),
            VendorCode::fromString('ACME'),
            VendorName::of('Acme Supply Co.'),
            VendorContact::of('Jane Smith'),
            EmailAddress::of('jane@acme.test'),
            PhoneNumber::of('+15551234567'),
            VendorAddress::of('123 Main St', null, 'Springfield', 'IL', '62701', 'US'),
            $this->clock,
        );
    }
}
