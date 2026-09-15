<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\ValueObject\MemberLineageKind;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class MemberLineageKindTest extends TestCase
{
    #[Test]
    #[TestWith([MemberLineageKind::MergedInto, 'MERGED_INTO'])]
    #[TestWith([MemberLineageKind::SplitFrom, 'SPLIT_FROM'])]
    #[TestDox('each case carries the expected wire value.')]
    public function case_values(MemberLineageKind $kind, string $expected): void
    {
        self::assertSame($expected, $kind->value);
    }
}
