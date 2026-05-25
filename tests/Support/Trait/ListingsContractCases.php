<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Catalog\Domain\Exception\DuplicateListingCode;
use App\Catalog\Domain\Exception\ListingNotFound;
use App\Catalog\Domain\Listing;
use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\Listings;
use App\Catalog\Domain\ValueObject\Currency;
use App\Catalog\Domain\ValueObject\Fee;
use App\Catalog\Domain\ValueObject\LedgerAccount;
use App\Catalog\Domain\ValueObject\ListingCode;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Domain\ValueObject\Money;
use App\Catalog\Domain\ValueObject\TaxTreatment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared behavioural contract for any {@see Listings} adapter. Concrete
 * test classes (`InMemoryListingsContractTest`,
 * `DoctrineListingsContractTest`) use this trait so the two
 * implementations cannot drift apart.
 */
trait ListingsContractCases
{
    private const ID_A = '019571bf-5d51-7000-b500-000000000010';
    private const ID_B = '019571bf-5d51-7000-b500-000000000011';
    private const ID_C = '019571bf-5d51-7000-b500-000000000012';

    abstract protected function listings(): Listings;

    abstract protected function clock(): MockClock;

    #[Test]
    #[TestDox('add() then byId() round-trips a listing with deep-equal fees, tax, ledger, and timestamps.')]
    public function add_then_by_id_round_trips(): void
    {
        $listing = $this->makeListing(self::ID_A, 'YOGA-101', ListingKind::Program, 'Beginner Yoga');
        $this->listings()->add($listing);

        $loaded = $this->listings()->byId(ListingId::fromString(self::ID_A));

        self::assertTrue($loaded->id()->equals($listing->id()));
        self::assertTrue($loaded->code()->equals(ListingCode::of('YOGA-101')));
        self::assertSame(ListingKind::Program, $loaded->kind());
        self::assertSame('Beginner Yoga', $loaded->name());
        self::assertCount(1, $loaded->fees());
        self::assertTrue($loaded->fees()[0]->equals(
            Fee::of(Money::ofCents(1500, Currency::USD), 'Adult'),
        ));
        self::assertTrue($loaded->taxTreatment()->equals(TaxTreatment::of(true, false)));
        self::assertTrue($loaded->ledgerAccount()->equals(LedgerAccount::of('4000-PRG')));
        self::assertFalse($loaded->isArchived());
        self::assertEquals($listing->registeredAt(), $loaded->registeredAt());
    }

    #[Test]
    #[TestDox('byCode() resolves a listing by its canonical code (case-insensitive on the lookup input).')]
    public function by_code_resolves_listing(): void
    {
        $this->listings()->add($this->makeListing(self::ID_A, 'SKU-1', ListingKind::Inventory, 'Towel'));

        $loaded = $this->listings()->byCode(ListingCode::of('sku-1'));

        self::assertTrue($loaded->id()->equals(ListingId::fromString(self::ID_A)));
        self::assertSame('SKU-1', $loaded->code()->value);
    }

    #[Test]
    #[TestDox('byId() throws ListingNotFound when no listing has the given id.')]
    public function by_id_missing_throws(): void
    {
        $this->expectException(ListingNotFound::class);

        $this->listings()->byId(ListingId::fromString(self::ID_A));
    }

    #[Test]
    #[TestDox('byCode() throws ListingNotFound when no listing has the given code.')]
    public function by_code_missing_throws(): void
    {
        $this->expectException(ListingNotFound::class);

        $this->listings()->byCode(ListingCode::of('NOPE'));
    }

    #[Test]
    #[TestDox('existsWithCode() is true after add() and false for an unknown code.')]
    public function exists_with_code_reflects_state(): void
    {
        self::assertFalse($this->listings()->existsWithCode(ListingCode::of('SKU-1')));

        $this->listings()->add($this->makeListing(self::ID_A, 'SKU-1', ListingKind::Inventory, 'Towel'));

        self::assertTrue($this->listings()->existsWithCode(ListingCode::of('SKU-1')));
        self::assertFalse($this->listings()->existsWithCode(ListingCode::of('SKU-2')));
    }

    #[Test]
    #[TestDox('add() raises DuplicateListingCode when a second listing reuses an existing code.')]
    public function add_duplicate_code_throws(): void
    {
        $this->listings()->add($this->makeListing(self::ID_A, 'SKU-1', ListingKind::Inventory, 'Towel'));

        $this->expectException(DuplicateListingCode::class);

        $this->listings()->add($this->makeListing(self::ID_B, 'SKU-1', ListingKind::Inventory, 'Other'));
    }

    #[Test]
    #[TestDox(
        'save() throws ListingNotFound when the listing has never been persisted '
        . '(callers must use add() for new aggregates).'
    )]
    public function save_unknown_listing_throws(): void
    {
        $listing = $this->makeListing(self::ID_A, 'NEW-1', ListingKind::Inventory, 'Brand new');

        $this->expectException(ListingNotFound::class);

        $this->listings()->save($listing);
    }

    #[Test]
    #[TestDox('save() persists modifications made through aggregate behaviour methods.')]
    public function save_persists_updates(): void
    {
        $listing = $this->makeListing(self::ID_A, 'YOGA-101', ListingKind::Program, 'Beginner Yoga');
        $this->listings()->add($listing);

        $loaded = $this->listings()->byId(ListingId::fromString(self::ID_A));
        $this->clock()->modify('+1 hour');
        $loaded->rename('Intermediate Yoga', $this->clock());
        $this->listings()->save($loaded);

        $again = $this->listings()->byId(ListingId::fromString(self::ID_A));
        self::assertSame('Intermediate Yoga', $again->name());
    }

    #[Test]
    #[TestDox('findByKind() returns only listings matching the kind, ordered by code ASC, applying offset and limit.')]
    public function find_by_kind_filters_and_paginates(): void
    {
        // Insert out of code order so the adapter is forced to sort.
        $this->listings()->add($this->makeListing(self::ID_B, 'PRG-2', ListingKind::Program, 'Pilates'));
        $this->listings()->add($this->makeListing(self::ID_A, 'PRG-1', ListingKind::Program, 'Yoga'));
        $this->listings()->add($this->makeListing(self::ID_C, 'INV-1', ListingKind::Inventory, 'Towel'));

        $page = $this->listings()->findByKind(ListingKind::Program, 0, 10);
        self::assertCount(2, $page);
        self::assertSame('PRG-1', $page[0]->code()->value);
        self::assertSame('PRG-2', $page[1]->code()->value);
        foreach ($page as $row) {
            self::assertSame(ListingKind::Program, $row->kind());
        }

        $single = $this->listings()->findByKind(ListingKind::Program, 0, 1);
        self::assertCount(1, $single);
        self::assertSame('PRG-1', $single[0]->code()->value);
    }

    #[Test]
    #[TestDox('searchByName() returns case-insensitive partial matches ordered by name ASC, with pagination.')]
    public function search_by_name_partial_matches(): void
    {
        // Insert out of alphabetical order so the adapter is forced to sort.
        $this->listings()->add($this->makeListing(self::ID_A, 'PRG-1', ListingKind::Program, 'Beginner Yoga'));
        $this->listings()->add($this->makeListing(self::ID_B, 'PRG-2', ListingKind::Program, 'Advanced Yoga'));
        $this->listings()->add($this->makeListing(self::ID_C, 'INV-1', ListingKind::Inventory, 'Towel'));

        $matches = $this->listings()->searchByName('yoga', 0, 10);
        self::assertCount(2, $matches);
        self::assertSame('Advanced Yoga', $matches[0]->name());
        self::assertSame('Beginner Yoga', $matches[1]->name());

        $empty = $this->listings()->searchByName('   ', 0, 10);
        self::assertSame([], $empty);
    }

    #[Test]
    #[TestDox('searchByName() treats LIKE wildcards (%, _) in the user input as literal characters.')]
    public function search_by_name_escapes_like_wildcards(): void
    {
        $this->listings()->add($this->makeListing(self::ID_A, 'PRG-1', ListingKind::Program, '50% Off Yoga'));
        $this->listings()->add($this->makeListing(self::ID_B, 'PRG-2', ListingKind::Program, 'Plain Yoga'));

        $percentLiteral = $this->listings()->searchByName('50%', 0, 10);
        self::assertCount(1, $percentLiteral);
        self::assertSame('50% Off Yoga', $percentLiteral[0]->name());

        $underscoreLiteral = $this->listings()->searchByName('_', 0, 10);
        self::assertSame([], $underscoreLiteral);
    }

    private function makeListing(
        string $id,
        string $code,
        ListingKind $kind,
        string $name,
    ): Listing {
        return Listing::register(
            ListingId::fromString($id),
            ListingCode::of($code),
            $kind,
            $name,
            [Fee::of(Money::ofCents(1500, Currency::USD), 'Adult')],
            TaxTreatment::of(true, false),
            LedgerAccount::of('4000-PRG'),
            $this->clock(),
        );
    }
}
