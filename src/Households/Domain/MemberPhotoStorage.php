<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\Exception\MemberPhotoStorageFailed;
use App\Households\Domain\ValueObject\ImageFormat;
use App\Households\Domain\ValueObject\MemberId;
use SplFileInfo;

/**
 * Domain port for persisting the byte content of a member's uploaded
 * profile photo (LRA-207). Implementations own storage-key allocation —
 * callers only supply the member id, the detected image format, and a
 * local source path (typically an uploaded file's temp path); the
 * adapter decides the final key shape.
 *
 * The local filesystem adapter ({@see \App\Households\Infrastructure\Storage\LocalMemberPhotoStorage})
 * is local/ephemeral storage; an S3-backed adapter is out of scope for
 * this port's first implementation and may be added later behind the
 * same interface.
 */
interface MemberPhotoStorage
{
    /**
     * Writes the file at $sourcePath into storage under a freshly
     * allocated key and returns that key.
     *
     * Key shape is part of this port's contract, not an adapter
     * implementation detail: `"<memberId>/<32 hex chars>.<extension>"`.
     * The `<32 hex chars>` segment is exactly what
     * {@see \App\Households\Domain\ValueObject\ProfilePhoto::version()}
     * returns, so HTTP callers that only have (memberId, version,
     * mimeType) — e.g. {@see \App\Households\Infrastructure\Http\Controller\MemberPhotoController})
     * — can reconstruct the key without a lookup.
     *
     * @throws MemberPhotoStorageFailed when the file cannot be written.
     */
    public function store(MemberId $memberId, ImageFormat $format, string $sourcePath): string;

    /**
     * @throws MemberPhotoStorageFailed when no file exists for $storageKey.
     */
    public function readable(string $storageKey): SplFileInfo;

    /**
     * Removes the file for $storageKey. Idempotent: removing an
     * already-absent key is not an error.
     */
    public function remove(string $storageKey): void;
}
