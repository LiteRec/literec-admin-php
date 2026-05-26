<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\VendorAddress;
use App\Inventory\Domain\ValueObject\VendorCode;
use App\Inventory\Domain\ValueObject\VendorContact;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Domain\ValueObject\VendorName;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;

final readonly class VendorRegistered
{
    public function __construct(
        public VendorId $vendorId,
        public VendorCode $code,
        public VendorName $name,
        public VendorContact $contact,
        public ?EmailAddress $email,
        public ?PhoneNumber $phone,
        public ?VendorAddress $address,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
