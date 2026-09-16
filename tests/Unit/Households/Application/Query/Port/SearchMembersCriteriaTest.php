<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Query\Port;

use App\Households\Application\Exception\InvalidMemberSearchPagination;
use App\Households\Application\Query\Port\MembersSegment;
use App\Households\Application\Query\Port\SearchMembersCriteria;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class SearchMembersCriteriaTest extends TestCase
{
    #[Test]
    #[TestDox('Constructor: accepts the default pagination (page = 1, pageSize = 20).')]
    public function constructor_accepts_default_pagination(): void
    {
        $criteria = new SearchMembersCriteria();

        self::assertSame(1, $criteria->page);
        self::assertSame(20, $criteria->pageSize);
    }

    #[Test]
    #[TestDox('Constructor: accepts pageSize at the lower and upper bounds.')]
    #[TestWith([1], 'pageSize = 1 (lower bound)')]
    #[TestWith([100], 'pageSize = 100 (upper bound)')]
    public function constructor_accepts_page_size_bounds(int $pageSize): void
    {
        $criteria = new SearchMembersCriteria(pageSize: $pageSize);

        self::assertSame($pageSize, $criteria->pageSize);
    }

    #[Test]
    #[TestDox('Constructor: rejects pageSize = 0 with InvalidMemberSearchPagination.')]
    public function constructor_rejects_zero_page_size(): void
    {
        $this->expectException(InvalidMemberSearchPagination::class);
        $this->expectExceptionMessage('pageSize');

        new SearchMembersCriteria(pageSize: 0); // NOSONAR — constructor is expected to throw.
    }

    #[Test]
    #[TestDox('Constructor: rejects pageSize = 101 with InvalidMemberSearchPagination.')]
    public function constructor_rejects_excessive_page_size(): void
    {
        $this->expectException(InvalidMemberSearchPagination::class);
        $this->expectExceptionMessage('pageSize');

        new SearchMembersCriteria(pageSize: 101); // NOSONAR — constructor is expected to throw.
    }

    #[Test]
    #[TestDox('Constructor: rejects page = 0 with InvalidMemberSearchPagination.')]
    public function constructor_rejects_zero_page(): void
    {
        $this->expectException(InvalidMemberSearchPagination::class);
        $this->expectExceptionMessage('page');

        new SearchMembersCriteria(page: 0); // NOSONAR — constructor is expected to throw.
    }

    #[Test]
    #[TestDox('Constructor: defaults segment to MembersSegment::All.')]
    public function constructor_defaults_segment_to_all(): void
    {
        $criteria = new SearchMembersCriteria();

        self::assertSame(MembersSegment::All, $criteria->segment);
    }

    #[Test]
    #[TestDox('Constructor: normalizes a blank q to null so it never becomes a LIKE \'%%\' filter.')]
    #[TestWith([null], 'null')]
    #[TestWith([''], 'empty string')]
    #[TestWith(['   '], 'whitespace only')]
    public function constructor_normalizes_blank_q_to_null(?string $q): void
    {
        $criteria = new SearchMembersCriteria(q: $q);

        self::assertNull($criteria->q);
    }

    #[Test]
    #[TestDox('Constructor: keeps a non-blank q as given.')]
    public function constructor_keeps_non_blank_q(): void
    {
        $criteria = new SearchMembersCriteria(q: 'smith');

        self::assertSame('smith', $criteria->q);
    }
}
