<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the "Add Member to Household" dialog
 * (LRA-40).
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor and
 * the Application command DTO is `final readonly`. This input is created and
 * consumed entirely inside the Households HTTP adapter.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class AddMemberInput
{
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

    public bool $isPrimary = false;
}
