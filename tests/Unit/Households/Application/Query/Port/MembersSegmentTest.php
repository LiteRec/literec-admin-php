<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Query\Port;

use App\Households\Application\Query\Port\MembersSegment;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class MembersSegmentTest extends TestCase
{
    #[Test]
    #[TestDox('fromRequestValue(): maps a known request value onto its case.')]
    #[TestWith(['residents', MembersSegment::Residents])]
    #[TestWith(['nonResidents', MembersSegment::NonResidents])]
    #[TestWith(['inactive', MembersSegment::Inactive])]
    #[TestWith(['all', MembersSegment::All])]
    public function from_request_value_maps_known_values(string $value, MembersSegment $expected): void
    {
        self::assertSame($expected, MembersSegment::fromRequestValue($value));
    }

    #[Test]
    #[TestDox('fromRequestValue(): falls back to All for null or an unrecognised value.')]
    #[TestWith([null], 'null')]
    #[TestWith(['bogus'], 'unrecognised string')]
    #[TestWith([''], 'empty string')]
    public function from_request_value_falls_back_to_all(?string $value): void
    {
        self::assertSame(MembersSegment::All, MembersSegment::fromRequestValue($value));
    }
}
