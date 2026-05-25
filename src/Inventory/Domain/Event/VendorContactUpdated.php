<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\VendorContact;
use App\Inventory\Domain\ValueObject\VendorId;
use DateTimeImmutable;

final readonly class VendorContactUpdated
{
    public function __construct(
        public VendorId $vendorId,
        public VendorContact $contact,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
