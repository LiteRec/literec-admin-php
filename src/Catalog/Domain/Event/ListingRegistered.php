<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\ValueObject\Fee;
use App\Catalog\Domain\ValueObject\LedgerAccount;
use App\Catalog\Domain\ValueObject\ListingCode;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Domain\ValueObject\TaxTreatment;
use DateTimeImmutable;

final readonly class ListingRegistered
{
    /**
     * @param list<Fee> $fees
     */
    public function __construct(
        public ListingId $listingId,
        public ListingCode $code,
        public ListingKind $kind,
        public string $name,
        public array $fees,
        public TaxTreatment $taxTreatment,
        public LedgerAccount $ledgerAccount,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
