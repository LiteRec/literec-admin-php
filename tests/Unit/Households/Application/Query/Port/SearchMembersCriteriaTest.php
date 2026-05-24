<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Query\Port;

use App\Households\Application\Query\Port\SearchMembersCriteria;
use InvalidArgumentException;
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
    #[TestDox('Constructor: rejects pageSize = 0 with InvalidArgumentException.')]
    public function constructor_rejects_zero_page_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pageSize');

        new SearchMembersCriteria(pageSize: 0);
    }

    #[Test]
    #[TestDox('Constructor: rejects pageSize = 101 with InvalidArgumentException.')]
    public function constructor_rejects_excessive_page_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pageSize');

        new SearchMembersCriteria(pageSize: 101);
    }

    #[Test]
    #[TestDox('Constructor: rejects page = 0 with InvalidArgumentException.')]
    public function constructor_rejects_zero_page(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('page');

        new SearchMembersCriteria(page: 0);
    }
}
