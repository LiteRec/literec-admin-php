<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\FacilityScopeEmpty;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\FacilityScope;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class FacilityScopeTest extends TestCase
{
    #[Test]
    #[TestDox('::all() includes any facility and reports isAll() true.')]
    public function all_scope_includes_everything(): void
    {
        $scope = FacilityScope::all();

        self::assertTrue($scope->isAll());
        self::assertTrue($scope->includes(FacilityCode::fromString('MAIN')));
        self::assertTrue($scope->includes(FacilityCode::fromString('LAKESIDE')));
        self::assertSame([], $scope->facilities);
    }

    #[Test]
    #[TestDox('::ofFacilities() includes only listed facilities and dedup-sorts canonical values.')]
    public function facility_scope_includes_listed_only(): void
    {
        $main = FacilityCode::fromString('MAIN');
        $lake = FacilityCode::fromString('LAKESIDE');

        $scope = FacilityScope::ofFacilities([$lake, $main, $lake]);

        self::assertFalse($scope->isAll());
        self::assertTrue($scope->includes($main));
        self::assertTrue($scope->includes($lake));
        self::assertFalse($scope->includes(FacilityCode::fromString('OTHER')));
        // Dedup-sorted: LAKESIDE < MAIN by ASCII; appears once each.
        self::assertCount(2, $scope->facilities);
        self::assertSame('LAKESIDE', $scope->facilities[0]->value);
        self::assertSame('MAIN', $scope->facilities[1]->value);
    }

    #[Test]
    #[TestDox('::ofFacilities() with an empty list throws FacilityScopeEmpty.')]
    public function ofFacilities_empty_throws(): void
    {
        $this->expectException(FacilityScopeEmpty::class);

        FacilityScope::ofFacilities([]);
    }

    #[Test]
    #[TestDox('::equals() compares by isAll flag and canonical facility set.')]
    public function equals(): void
    {
        $a = FacilityScope::ofFacilities([FacilityCode::fromString('MAIN')]);
        $b = FacilityScope::ofFacilities([FacilityCode::fromString('MAIN')]);
        $c = FacilityScope::ofFacilities([FacilityCode::fromString('LAKESIDE')]);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($a->equals(FacilityScope::all()));
    }
}
