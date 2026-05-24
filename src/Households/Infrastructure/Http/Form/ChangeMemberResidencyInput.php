<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the Residency sub-card change form (LRA-44).
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor and
 * the Application command DTO
 * {@see \App\Households\Application\Command\ChangeMemberResidency} is
 * `final readonly`. Created and consumed entirely inside the Households
 * HTTP adapter.
 *
 * `reason` is optional — operations staff may leave it blank for routine
 * status updates that need no audit note.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class ChangeMemberResidencyInput
{
    public ?string $residencyStatusCode = null;

    public ?string $effectiveFromIso = null;

    public ?string $reason = null;
}
