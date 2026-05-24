<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the Address sub-card edit form (LRA-44).
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor and
 * the Application command DTO
 * {@see \App\Households\Application\Command\UpdateHouseholdAddress} is
 * `final readonly`. This input is created and consumed entirely inside the
 * Households HTTP adapter.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class UpdateHouseholdAddressInput
{
    public ?string $street = null;

    public ?string $unit = null;

    public ?string $city = null;

    public ?string $state = null;

    public ?string $postalCode = null;

    public ?string $country = null;
}
