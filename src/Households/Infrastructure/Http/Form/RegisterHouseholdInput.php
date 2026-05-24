<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the "Register Household" dialog (LRA-40).
 *
 * Symfony's form component writes back into a `data_class` instance through
 * the PropertyAccessor; that requires writable (non-readonly) properties.
 * The Application layer's command DTO {@see
 * \App\Households\Application\Command\RegisterHousehold} stays
 * `final readonly` per the immutability rules, so this Infrastructure-only
 * adapter exists purely to receive form input and is then transposed into
 * the command DTO inside the controller.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class RegisterHouseholdInput
{
    public ?string $householdName = null;

    public ?string $firstName = null;

    public ?string $lastName = null;

    public ?string $middleName = null;

    public ?string $suffix = null;

    public ?string $dobIso = null;

    public ?string $genderCode = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $residencyStatusCode = null;

    public ?string $memberCode = null;

    public ?string $street = null;

    public ?string $unit = null;

    public ?string $city = null;

    public ?string $state = null;

    public ?string $postalCode = null;

    public ?string $country = 'US';
}
