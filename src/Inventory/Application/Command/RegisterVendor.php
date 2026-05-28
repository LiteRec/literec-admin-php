<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for the RegisterVendor use case (LRA-92).
 *
 * Introduced by the fixtures ticket because every fixture write must
 * flow through the command bus and Vendor was previously the only
 * Inventory aggregate without a registration command. Value-object
 * construction happens inside the handler so invalid input surfaces
 * as a named domain exception rather than a TypeError at the bus
 * boundary.
 *
 * Address is optional and modelled as a primitive sub-DTO array so
 * the DTO stays trivially serializable across the bus.
 *
 * @phpstan-type AddressInput array{
 *     street: string,
 *     unit: ?string,
 *     city: string,
 *     state: string,
 *     postalCode: string,
 *     country: string,
 * }
 */
final readonly class RegisterVendor
{
    /**
     * @param AddressInput|null $address
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $contact,
        public ?string $email,
        public ?string $phone,
        public ?array $address,
    ) {
    }
}
