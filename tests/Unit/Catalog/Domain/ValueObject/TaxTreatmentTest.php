<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Exception\InvalidTaxTreatment;
use App\Catalog\Domain\ValueObject\TaxTreatment;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class TaxTreatmentTest extends TestCase
{
    #[Test]
    #[TestWith([true, true], 'tax applied and included')]
    #[TestWith([true, false], 'tax applied and added on top')]
    #[TestWith([false, false], 'no tax at all')]
    #[TestDox('Accepts the three valid (applyTax, taxIncludedInFee) combinations: $_dataName.')]
    public function accepts_valid_combinations(bool $applyTax, bool $taxIncludedInFee): void
    {
        $tt = TaxTreatment::of($applyTax, $taxIncludedInFee);

        self::assertSame($applyTax, $tt->applyTax);
        self::assertSame($taxIncludedInFee, $tt->taxIncludedInFee);
    }

    #[Test]
    #[TestDox('Rejects taxIncludedInFee=true when applyTax=false (no tax to include).')]
    public function rejects_included_without_applied(): void
    {
        $this->expectException(InvalidTaxTreatment::class);

        TaxTreatment::of(false, true);
    }

    #[Test]
    #[TestDox('TaxTreatment::none produces a no-tax instance.')]
    public function none_factory(): void
    {
        $tt = TaxTreatment::none();

        self::assertFalse($tt->applyTax);
        self::assertFalse($tt->taxIncludedInFee);
    }

    #[Test]
    #[TestDox('Equals another TaxTreatment with matching applyTax and taxIncludedInFee.')]
    public function equals_compares_both_flags(): void
    {
        $a = TaxTreatment::of(true, false);
        $b = TaxTreatment::of(true, false);
        $c = TaxTreatment::of(true, true);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
