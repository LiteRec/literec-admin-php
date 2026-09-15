<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the Deactivate Member confirmation
 * dialog (LRA-211).
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor and
 * the Application command DTO
 * {@see \App\Households\Application\Command\DeactivateMember} is
 * `final readonly`. Created and consumed entirely inside the Households
 * HTTP adapter.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class DeactivateMemberInput
{
    public ?string $reason = null;
}
