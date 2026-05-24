<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the Profile card edit form (LRA-43).
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor and
 * the Application command DTO
 * {@see \App\Households\Application\Command\UpdateMemberProfile} is
 * `final readonly`. This input is created and consumed entirely inside the
 * Households HTTP adapter.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class UpdateMemberProfileInput
{
    public ?string $firstName = null;

    public ?string $middleName = null;

    public ?string $lastName = null;

    public ?string $suffix = null;

    public ?string $dobIso = null;

    public ?string $genderCode = null;
}
