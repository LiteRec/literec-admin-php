<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the "set a new password" page (LRA-213).
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor and
 * the Application command DTO
 * {@see \App\Users\Application\Command\EstablishPassword} is `final readonly`.
 * This input is created and consumed entirely inside the Users HTTP adapter.
 *
 * @internal Belongs to the Users HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class EstablishPasswordInput
{
    public ?string $newPassword = null;
}
