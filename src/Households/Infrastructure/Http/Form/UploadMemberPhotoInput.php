<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Symfony Form binding target for the Profile card photo upload (LRA-207).
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor and
 * the Application command DTO
 * {@see \App\Households\Application\Command\AttachMemberPhoto} is
 * `final readonly`. This input is created and consumed entirely inside the
 * Households HTTP adapter.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class UploadMemberPhotoInput
{
    public ?UploadedFile $photo = null;
}
