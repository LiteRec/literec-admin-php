<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\VendorAddress;
use App\Inventory\Domain\ValueObject\VendorId;
use DateTimeImmutable;

final readonly class VendorAddressUpdated
{
    public function __construct(
        public VendorId $vendorId,
        public ?VendorAddress $address,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
