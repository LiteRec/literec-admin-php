<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Domain\ValueObject\VendorName;
use DateTimeImmutable;

final readonly class VendorRenamed
{
    public function __construct(
        public VendorId $vendorId,
        public VendorName $name,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
