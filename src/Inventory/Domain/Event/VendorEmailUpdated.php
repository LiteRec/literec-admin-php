<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\EmailAddress;
use App\Inventory\Domain\ValueObject\VendorId;
use DateTimeImmutable;

final readonly class VendorEmailUpdated
{
    public function __construct(
        public VendorId $vendorId,
        public ?EmailAddress $email,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
