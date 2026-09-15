<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

/**
 * Command bus message for uploading (or replacing) a member's profile
 * photo (LRA-207). $sourcePath is the local filesystem path of the
 * already-uploaded file (an {@see \Symfony\Component\HttpFoundation\File\UploadedFile}'s
 * temp path); $mimeType is the format the HTTP boundary detected.
 */
final readonly class AttachMemberPhoto
{
    public function __construct(
        public string $householdId,
        public string $memberId,
        public string $sourcePath,
        public string $mimeType,
    ) {
    }
}
