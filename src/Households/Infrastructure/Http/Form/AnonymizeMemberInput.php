<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the Anonymize Member confirmation dialog
 * (LRA-212).
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor and
 * the Application command DTO
 * {@see \App\Households\Application\Command\AnonymizeMember} is
 * `final readonly` and carries no confirmation fields at all — the
 * typed-name match and acknowledgement are an HTTP-boundary concern that
 * never reaches the command bus. Created and consumed entirely inside the
 * Households HTTP adapter.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class AnonymizeMemberInput
{
    public ?string $confirmation = null;

    public bool $acknowledged = false;
}
