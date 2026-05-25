<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Inventory\Domain\Exception\DuplicateVendorCode;
use App\Inventory\Domain\Exception\VendorNotFound;
use App\Inventory\Domain\Vendor;
use App\Inventory\Domain\Vendors;
use App\Inventory\Domain\ValueObject\EmailAddress;
use App\Inventory\Domain\ValueObject\PhoneNumber;
use App\Inventory\Domain\ValueObject\VendorAddress;
use App\Inventory\Domain\ValueObject\VendorCode;
use App\Inventory\Domain\ValueObject\VendorContact;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Domain\ValueObject\VendorName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared behavioural contract for any {@see Vendors} adapter. Concrete
 * test classes (`InMemoryVendorsContractTest`,
 * `DoctrineVendorsContractTest`) use this trait so the two
 * implementations cannot drift apart.
 */
trait VendorsContractCases
{
    private const ID_A = '019571bf-5d51-7000-b500-000000000030';
    private const ID_B = '019571bf-5d51-7000-b500-000000000031';
    private const ID_C = '019571bf-5d51-7000-b500-000000000032';

    abstract protected function vendors(): Vendors;

    abstract protected function clock(): MockClock;

    #[Test]
    #[TestDox(
        'add() then byId() round-trips a vendor with deep-equal contact, '
        . 'optional email/phone, and embedded address.'
    )]
    public function add_then_by_id_round_trips(): void
    {
        $vendor = $this->makeVendor(self::ID_A, 'ACME', 'Acme Supply Co.');
        $this->vendors()->add($vendor);

        $loaded = $this->vendors()->byId(VendorId::fromString(self::ID_A));

        self::assertTrue($loaded->id()->equals($vendor->id()));
        self::assertTrue($loaded->code()->equals(VendorCode::fromString('ACME')));
        self::assertSame('Acme Supply Co.', $loaded->name()->value);
        self::assertSame('Jane Smith', $loaded->contact()->value);
        self::assertNotNull($loaded->email());
        self::assertSame('jane@acme.test', $loaded->email()->value);
        self::assertNotNull($loaded->phone());
        self::assertSame('+15551234567', $loaded->phone()->value);
        self::assertNotNull($loaded->address());
        self::assertSame('123 Main St', $loaded->address()->street);
        self::assertSame('US', $loaded->address()->country);
        self::assertFalse($loaded->isArchived());
        self::assertEquals($vendor->registeredAt(), $loaded->registeredAt());
    }

    #[Test]
    #[TestDox('byCode() resolves a vendor by its canonical uppercase code.')]
    public function by_code_resolves_vendor(): void
    {
        $this->vendors()->add($this->makeVendor(self::ID_A, 'ACME', 'Acme'));

        $loaded = $this->vendors()->byCode(VendorCode::fromString('ACME'));

        self::assertTrue($loaded->id()->equals(VendorId::fromString(self::ID_A)));
        self::assertSame('ACME', $loaded->code()->value);
    }

    #[Test]
    #[TestDox('byId() throws VendorNotFound when no vendor has the given id.')]
    public function by_id_missing_throws(): void
    {
        $this->expectException(VendorNotFound::class);

        $this->vendors()->byId(VendorId::fromString(self::ID_A));
    }

    #[Test]
    #[TestDox('byCode() throws VendorNotFound when no vendor has the given code.')]
    public function by_code_missing_throws(): void
    {
        $this->expectException(VendorNotFound::class);

        $this->vendors()->byCode(VendorCode::fromString('NOPE'));
    }

    #[Test]
    #[TestDox('existsWithCode() is true after add() and false for an unknown code.')]
    public function exists_with_code_reflects_state(): void
    {
        self::assertFalse($this->vendors()->existsWithCode(VendorCode::fromString('ACME')));

        $this->vendors()->add($this->makeVendor(self::ID_A, 'ACME', 'Acme'));

        self::assertTrue($this->vendors()->existsWithCode(VendorCode::fromString('ACME')));
        self::assertFalse($this->vendors()->existsWithCode(VendorCode::fromString('OTHER')));
    }

    #[Test]
    #[TestDox('add() raises DuplicateVendorCode when a second vendor reuses an existing code.')]
    public function add_duplicate_code_throws(): void
    {
        $this->vendors()->add($this->makeVendor(self::ID_A, 'ACME', 'Acme'));

        $this->expectException(DuplicateVendorCode::class);

        $this->vendors()->add($this->makeVendor(self::ID_B, 'ACME', 'Other'));
    }

    #[Test]
    #[TestDox(
        'save() throws VendorNotFound when the vendor has never been persisted '
        . '(callers must use add() for new aggregates).'
    )]
    public function save_unknown_vendor_throws(): void
    {
        $vendor = $this->makeVendor(self::ID_A, 'NEW1', 'Brand New');

        $this->expectException(VendorNotFound::class);

        $this->vendors()->save($vendor);
    }

    #[Test]
    #[TestDox('save() persists modifications made through aggregate behaviour methods.')]
    public function save_persists_updates(): void
    {
        $vendor = $this->makeVendor(self::ID_A, 'ACME', 'Acme Supply Co.');
        $this->vendors()->add($vendor);

        $loaded = $this->vendors()->byId(VendorId::fromString(self::ID_A));
        $this->clock()->modify('+1 hour');
        $loaded->rename(VendorName::of('Acme Inc.'), $this->clock());
        $this->vendors()->save($loaded);

        $again = $this->vendors()->byId(VendorId::fromString(self::ID_A));
        self::assertSame('Acme Inc.', $again->name()->value);
    }

    #[Test]
    #[TestDox('searchByName() returns case-insensitive partial matches ordered by name ASC, with pagination.')]
    public function search_by_name_partial_matches(): void
    {
        // Insert out of alphabetical order so the adapter is forced to sort.
        $this->vendors()->add($this->makeVendor(self::ID_A, 'V1', 'Yoga Gear Co.'));
        $this->vendors()->add($this->makeVendor(self::ID_B, 'V2', 'Acme Yoga Supplies'));
        $this->vendors()->add($this->makeVendor(self::ID_C, 'V3', 'Towel Town'));

        $matches = $this->vendors()->searchByName('yoga', 0, 10);
        self::assertCount(2, $matches);
        self::assertSame('Acme Yoga Supplies', $matches[0]->name()->value);
        self::assertSame('Yoga Gear Co.', $matches[1]->name()->value);

        $empty = $this->vendors()->searchByName('   ', 0, 10);
        self::assertSame([], $empty);
    }

    #[Test]
    #[TestDox('searchByName() treats LIKE wildcards (%, _) in the user input as literal characters.')]
    public function search_by_name_escapes_like_wildcards(): void
    {
        $this->vendors()->add($this->makeVendor(self::ID_A, 'V1', '50% Off Vendor'));
        $this->vendors()->add($this->makeVendor(self::ID_B, 'V2', 'Plain Vendor'));

        $percentLiteral = $this->vendors()->searchByName('50%', 0, 10);
        self::assertCount(1, $percentLiteral);
        self::assertSame('50% Off Vendor', $percentLiteral[0]->name()->value);

        $underscoreLiteral = $this->vendors()->searchByName('_', 0, 10);
        self::assertSame([], $underscoreLiteral);
    }

    #[Test]
    #[TestDox('listActive() returns only non-archived vendors ordered by code ASC.')]
    public function list_active_filters_and_orders(): void
    {
        $this->vendors()->add($this->makeVendor(self::ID_B, 'BVEND', 'Bravo'));
        $this->vendors()->add($this->makeVendor(self::ID_A, 'AVEND', 'Alpha'));
        $cv = $this->makeVendor(self::ID_C, 'CVEND', 'Charlie');
        $cv->archive($this->clock());
        $this->vendors()->add($cv);

        $page = $this->vendors()->listActive(0, 10);

        self::assertCount(2, $page);
        self::assertSame('AVEND', $page[0]->code()->value);
        self::assertSame('BVEND', $page[1]->code()->value);
    }

    #[Test]
    #[TestDox('listActive() applies offset and limit.')]
    public function list_active_paginates(): void
    {
        $this->vendors()->add($this->makeVendor(self::ID_A, 'AVEND', 'Alpha'));
        $this->vendors()->add($this->makeVendor(self::ID_B, 'BVEND', 'Bravo'));
        $this->vendors()->add($this->makeVendor(self::ID_C, 'CVEND', 'Charlie'));

        $single = $this->vendors()->listActive(0, 1);
        self::assertCount(1, $single);
        self::assertSame('AVEND', $single[0]->code()->value);

        $second = $this->vendors()->listActive(1, 1);
        self::assertCount(1, $second);
        self::assertSame('BVEND', $second[0]->code()->value);
    }

    private function makeVendor(string $id, string $code, string $name): Vendor
    {
        return Vendor::register(
            VendorId::fromString($id),
            VendorCode::fromString($code),
            VendorName::of($name),
            VendorContact::of('Jane Smith'),
            EmailAddress::of('jane@acme.test'),
            PhoneNumber::of('+15551234567'),
            VendorAddress::of('123 Main St', null, 'Springfield', 'IL', '62701', 'US'),
            $this->clock(),
        );
    }
}
