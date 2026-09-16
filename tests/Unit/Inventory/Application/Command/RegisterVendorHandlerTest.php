<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Inventory\Application\Command\RegisterVendor;
use App\Inventory\Application\Command\RegisterVendorHandler;
use App\Inventory\Domain\Event\VendorRegistered;
use App\Inventory\Domain\Exception\InvalidVendorAddress;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryVendors;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Fake\SequenceInventoryIdentityGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class RegisterVendorHandlerTest extends TestCase
{
    private const VENDOR_ID = '019571bf-5d51-7000-b500-000000003001';

    private InMemoryVendors $vendors;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;
    private SequenceInventoryIdentityGenerator $ids;

    protected function setUp(): void
    {
        $this->vendors = new InMemoryVendors();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
        $this->ids = new SequenceInventoryIdentityGenerator(
            vendorIds: [VendorId::fromString(self::VENDOR_ID)],
        );
    }

    #[Test]
    #[TestDox('Registering a vendor whose address row is missing a required field throws InvalidVendorAddress.')]
    public function it_rejects_an_address_row_missing_a_required_field(): void
    {
        $handler = new RegisterVendorHandler($this->vendors, $this->ids, $this->clock, $this->eventBus);

        $this->expectException(InvalidVendorAddress::class);

        // City is intentionally omitted to exercise the runtime guard for a
        // malformed bus payload; AddressInput is a doc-only annotation, not
        // a runtime check.
        ($handler)(new RegisterVendor(
            code: 'ACME',
            name: 'Acme',
            contact: 'Jane',
            email: null,
            phone: null,
            // @phpstan-ignore argument.type
            address: [
                'street' => '123 Main St',
                'unit' => null,
                'state' => 'IL',
                'postalCode' => '62701',
                'country' => 'US',
            ],
        ));
    }

    #[Test]
    #[TestDox('RegisterVendor stores the vendor with its address and dispatches VendorRegistered.')]
    public function it_registers_a_vendor_with_its_address(): void
    {
        $handler = new RegisterVendorHandler($this->vendors, $this->ids, $this->clock, $this->eventBus);

        $id = ($handler)(new RegisterVendor(
            code: 'ACME',
            name: 'Acme',
            contact: 'Jane',
            email: null,
            phone: null,
            address: [
                'street' => '123 Main St',
                'unit' => null,
                'city' => 'Springfield',
                'state' => 'IL',
                'postalCode' => '62701',
                'country' => 'US',
            ],
        ));

        self::assertSame(self::VENDOR_ID, $id->value);

        $vendor = $this->vendors->byId($id);
        self::assertNotNull($vendor->address());
        self::assertSame('123 Main St', $vendor->address()->street);
        self::assertSame('Springfield', $vendor->address()->city);

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(VendorRegistered::class, $messages[0]);
        self::assertSame(self::VENDOR_ID, $messages[0]->vendorId->value);
    }
}
