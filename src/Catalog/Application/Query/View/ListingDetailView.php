<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

use DateTimeImmutable;

final readonly class ListingDetailView
{
    /**
     * @param list<FeeView> $fees
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $kind,
        public string $name,
        public array $fees,
        public bool $taxApply,
        public bool $taxIncludedInFee,
        public string $ledgerAccount,
        public bool $archived,
        public DateTimeImmutable $registeredAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
