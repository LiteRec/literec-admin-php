<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the Contact sub-card edit form (LRA-204).
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor and
 * the Application command DTO
 * {@see \App\Households\Application\Command\UpdateMemberContact} is
 * `final readonly`. This input is created and consumed entirely inside the
 * Households HTTP adapter.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class UpdateMemberContactInput
{
    public ?string $email = null;

    public ?string $phone = null;
}
