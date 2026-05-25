<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\LedgerAccount;
use App\Catalog\Domain\ValueObject\ListingId;
use DateTimeImmutable;

final readonly class ListingLedgerAccountUpdated
{
    public function __construct(
        public ListingId $listingId,
        public LedgerAccount $ledgerAccount,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
