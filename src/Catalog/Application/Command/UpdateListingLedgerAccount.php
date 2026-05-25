<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

final readonly class UpdateListingLedgerAccount
{
    public function __construct(
        public string $listingId,
        public string $ledgerAccount,
    ) {
    }
}
