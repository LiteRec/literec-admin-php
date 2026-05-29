<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Event\VendorAddressUpdated;
use App\Inventory\Domain\Event\VendorArchived;
use App\Inventory\Domain\Event\VendorContactUpdated;
use App\Inventory\Domain\Event\VendorEmailUpdated;
use App\Inventory\Domain\Event\VendorPhoneUpdated;
use App\Inventory\Domain\Event\VendorRegistered;
use App\Inventory\Domain\Event\VendorRenamed;
use App\Inventory\Domain\Exception\VendorIsArchived;
use App\Inventory\Domain\ValueObject\VendorAddress;
use App\Inventory\Domain\ValueObject\VendorCode;
use App\Inventory\Domain\ValueObject\VendorContact;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Domain\ValueObject\VendorName;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Inventory Vendor aggregate.
 *
 * Pure domain class: no Symfony or Doctrine imports. State changes flow
 * through intention-revealing methods that buffer domain events; the
 * application service releases them after the persistence transaction
 * commits.
 *
 * Vendors are referenced by {@see VendorId} (published-language identity)
 * from other Inventory aggregates ({@see App\Inventory\Domain\InventoryItem}
 * in LRA-73/76, {@see App\Inventory\Domain\PurchaseOrder} in LRA-77).
 * Cross-aggregate references go through the identity value object — never
 * through a Doctrine association.
 */
final class Vendor
{
    use AggregateRoot;
    use NullSafeEquality;

    private VendorId $id;

    private VendorCode $code;

    private VendorName $name;

    private VendorContact $contact;

    private ?EmailAddress $email;

    private ?PhoneNumber $phone;

    private ?VendorAddress $address;

    private bool $archived;

    private DateTimeImmutable $registeredAt;

    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
        // Intentionally empty. Construction goes through the
        // self::register(...) named factory so every Vendor is born
        // valid and emits a VendorRegistered domain event; direct
        // instantiation is impossible.
    }

    public static function register(
        VendorId $id,
        VendorCode $code,
        VendorName $name,
        VendorContact $contact,
        ?EmailAddress $email,
        ?PhoneNumber $phone,
        ?VendorAddress $address,
        ClockInterface $clock,
    ): self {
        $vendor = new self();
        $vendor->id = $id;
        $vendor->code = $code;
        $vendor->name = $name;
        $vendor->contact = $contact;
        $vendor->email = $email;
        $vendor->phone = $phone;
        $vendor->address = $address;
        $vendor->archived = false;
        $vendor->registeredAt = $clock->now();
        $vendor->updatedAt = $vendor->registeredAt;

        $vendor->recordThat(new VendorRegistered(
            $id,
            $code,
            $name,
            $contact,
            $email,
            $phone,
            $address,
            $vendor->registeredAt,
        ));

        return $vendor;
    }

    public function id(): VendorId
    {
        return $this->id;
    }

    public function code(): VendorCode
    {
        return $this->code;
    }

    public function name(): VendorName
    {
        return $this->name;
    }

    public function contact(): VendorContact
    {
        return $this->contact;
    }

    public function email(): ?EmailAddress
    {
        return $this->email;
    }

    public function phone(): ?PhoneNumber
    {
        return $this->phone;
    }

    public function address(): ?VendorAddress
    {
        return $this->address;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function rename(VendorName $name, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if ($this->name->equals($name)) {
            return;
        }

        $this->name = $name;
        $this->updatedAt = $clock->now();
        $this->recordThat(new VendorRenamed($this->id, $name, $this->updatedAt));
    }

    public function updateContact(VendorContact $contact, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if ($this->contact->equals($contact)) {
            return;
        }

        $this->contact = $contact;
        $this->updatedAt = $clock->now();
        $this->recordThat(new VendorContactUpdated($this->id, $contact, $this->updatedAt));
    }

    public function updateEmail(?EmailAddress $email, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if (
            self::nullSafeEquals(
                $this->email,
                $email,
                static fn (EmailAddress $left, EmailAddress $right): bool => $left->equals($right),
            )
        ) {
            return;
        }

        $this->email = $email;
        $this->updatedAt = $clock->now();
        $this->recordThat(new VendorEmailUpdated($this->id, $email, $this->updatedAt));
    }

    public function updatePhone(?PhoneNumber $phone, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if (
            self::nullSafeEquals(
                $this->phone,
                $phone,
                static fn (PhoneNumber $left, PhoneNumber $right): bool => $left->equals($right),
            )
        ) {
            return;
        }

        $this->phone = $phone;
        $this->updatedAt = $clock->now();
        $this->recordThat(new VendorPhoneUpdated($this->id, $phone, $this->updatedAt));
    }

    public function updateAddress(?VendorAddress $address, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if (
            self::nullSafeEquals(
                $this->address,
                $address,
                static fn (VendorAddress $left, VendorAddress $right): bool => $left->equals($right),
            )
        ) {
            return;
        }

        $this->address = $address;
        $this->updatedAt = $clock->now();
        $this->recordThat(new VendorAddressUpdated($this->id, $address, $this->updatedAt));
    }

    public function archive(ClockInterface $clock): void
    {
        if ($this->archived) {
            return;
        }

        $this->archived = true;
        $this->updatedAt = $clock->now();
        $this->recordThat(new VendorArchived($this->id, $this->updatedAt));
    }

    private function guardNotArchived(): void
    {
        if ($this->archived) {
            throw VendorIsArchived::for($this->id);
        }
    }
}
