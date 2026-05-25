<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\PhoneNumber;
use App\Inventory\Domain\ValueObject\VendorId;
use DateTimeImmutable;

final readonly class VendorPhoneUpdated
{
    public function __construct(
        public VendorId $vendorId,
        public ?PhoneNumber $phone,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
