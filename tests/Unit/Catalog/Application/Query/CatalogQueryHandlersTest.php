<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\FindListingByCode;
use App\Catalog\Application\Query\FindListingByCodeHandler;
use App\Catalog\Application\Query\FindListingsByKind;
use App\Catalog\Application\Query\FindListingsByKindHandler;
use App\Catalog\Application\Query\GetListingDetail;
use App\Catalog\Application\Query\GetListingDetailHandler;
use App\Catalog\Application\Query\View\ListingDetailView;
use App\Catalog\Application\Query\View\ListingSummaryView;
use App\Catalog\Domain\Exception\ListingNotFound;
use App\Catalog\Domain\Listing;
use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\ValueObject\Currency;
use App\Catalog\Domain\ValueObject\Fee;
use App\Catalog\Domain\ValueObject\LedgerAccount;
use App\Catalog\Domain\ValueObject\ListingCode;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Domain\ValueObject\Money;
use App\Catalog\Domain\ValueObject\TaxTreatment;
use App\Catalog\Infrastructure\Persistence\InMemory\InMemoryListings;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class CatalogQueryHandlersTest extends TestCase
{
    private const string ID_A = '019571bf-5d51-7000-b500-0000000000a1';
    private const string ID_B = '019571bf-5d51-7000-b500-0000000000a2';
    private const string ID_C = '019571bf-5d51-7000-b500-0000000000a3';

    private InMemoryListings $listings;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->listings = new InMemoryListings();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-25 12:00:00'));
    }

    #[Test]
    #[TestDox('FindListingByCodeHandler returns a ListingSummaryView for an existing code.')]
    public function find_by_code_returns_summary(): void
    {
        $this->listings->add($this->makeListing(self::ID_A, 'YOGA-101', ListingKind::Program, 'Beginner Yoga'));

        $handler = new FindListingByCodeHandler($this->listings);
        $view = ($handler)(new FindListingByCode('YOGA-101'));

        self::assertInstanceOf(ListingSummaryView::class, $view);
        self::assertSame(self::ID_A, $view->id);
        self::assertSame('YOGA-101', $view->code);
        self::assertSame('PROGRAM', $view->kind);
        self::assertSame('Beginner Yoga', $view->name);
        self::assertFalse($view->archived);
    }

    #[Test]
    #[TestDox('FindListingByCodeHandler throws ListingNotFound when the code does not exist.')]
    public function find_by_code_throws_when_missing(): void
    {
        $handler = new FindListingByCodeHandler($this->listings);

        $this->expectException(ListingNotFound::class);

        ($handler)(new FindListingByCode('NOPE'));
    }

    #[Test]
    #[TestDox('FindListingsByKindHandler returns only summaries matching the kind, in code order, with pagination.')]
    public function find_by_kind_returns_summaries(): void
    {
        $this->listings->add($this->makeListing(self::ID_B, 'PRG-2', ListingKind::Program, 'Pilates'));
        $this->listings->add($this->makeListing(self::ID_A, 'PRG-1', ListingKind::Program, 'Yoga'));
        $this->listings->add($this->makeListing(self::ID_C, 'INV-1', ListingKind::Inventory, 'Towel'));

        $handler = new FindListingsByKindHandler($this->listings);
        $page = ($handler)(new FindListingsByKind('PROGRAM', 0, 10));

        self::assertCount(2, $page);
        self::assertSame('PRG-1', $page[0]->code);
        self::assertSame('PRG-2', $page[1]->code);
        foreach ($page as $row) {
            self::assertSame('PROGRAM', $row->kind);
        }

        $single = ($handler)(new FindListingsByKind('PROGRAM', 0, 1));
        self::assertCount(1, $single);
        self::assertSame('PRG-1', $single[0]->code);

        $second = ($handler)(new FindListingsByKind('PROGRAM', 1, 1));
        self::assertCount(1, $second);
        self::assertSame('PRG-2', $second[0]->code);

        $beyondLast = ($handler)(new FindListingsByKind('PROGRAM', 2, 10));
        self::assertSame([], $beyondLast);
    }

    #[Test]
    #[TestDox('FindListingsByKind constructor rejects negative offset and non-positive limit at the bus boundary.')]
    public function find_by_kind_rejects_bad_pagination(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $query = new FindListingsByKind('PROGRAM', -1, 10);
        self::fail(sprintf('Expected exception was not thrown; got %s.', $query::class));
    }

    #[Test]
    #[TestDox('FindListingsByKind constructor rejects a zero limit.')]
    public function find_by_kind_rejects_zero_limit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $query = new FindListingsByKind('PROGRAM', 0, 0);
        self::fail(sprintf('Expected exception was not thrown; got %s.', $query::class));
    }

    #[Test]
    #[TestDox('GetListingDetailHandler returns a ListingDetailView with full state including fees and tax.')]
    public function get_detail_returns_full_view(): void
    {
        $this->listings->add($this->makeListing(self::ID_A, 'YOGA-101', ListingKind::Program, 'Beginner Yoga'));

        $handler = new GetListingDetailHandler($this->listings);
        $view = ($handler)(new GetListingDetail(self::ID_A));

        self::assertInstanceOf(ListingDetailView::class, $view);
        self::assertSame(self::ID_A, $view->id);
        self::assertSame('YOGA-101', $view->code);
        self::assertSame('PROGRAM', $view->kind);
        self::assertSame('Beginner Yoga', $view->name);
        self::assertCount(1, $view->fees);
        self::assertSame(1500, $view->fees[0]->amountCents);
        self::assertSame('USD', $view->fees[0]->currency);
        self::assertSame('Adult', $view->fees[0]->label);
        self::assertTrue($view->taxApply);
        self::assertFalse($view->taxIncludedInFee);
        self::assertSame('4000-PRG', $view->ledgerAccount);
        self::assertFalse($view->archived);
        self::assertEquals($this->clock->now(), $view->registeredAt);
        self::assertEquals($this->clock->now(), $view->updatedAt);
    }

    #[Test]
    #[TestDox('GetListingDetailHandler throws ListingNotFound when the id does not exist.')]
    public function get_detail_throws_when_missing(): void
    {
        $handler = new GetListingDetailHandler($this->listings);

        $this->expectException(ListingNotFound::class);

        ($handler)(new GetListingDetail(self::ID_A));
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
            $this->clock,
        );
    }
}
