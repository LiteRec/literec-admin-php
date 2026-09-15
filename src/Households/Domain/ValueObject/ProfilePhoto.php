<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidProfilePhoto;
use DateTimeImmutable;

/**
 * A member's uploaded profile photo (LRA-207): where it lives in storage,
 * what format it is, and when it was uploaded. Validated once at
 * construction via {@see self::of()}; {@see \App\Households\Domain\HouseholdMember::photo()}
 * re-materializes this value object from persisted scalar fields without
 * re-validating, the same pattern {@see Deactivation} uses.
 */
final readonly class ProfilePhoto
{
    private const string ALLOWED_PATTERN = '/^[A-Za-z0-9_\-.\/]+$/';

    public function __construct(
        public string $storageKey,
        public ImageFormat $format,
        public DateTimeImmutable $uploadedAt,
    ) {
    }

    /**
     * @throws InvalidProfilePhoto when $storageKey is empty, contains
     *                             characters outside [A-Za-z0-9_-./], or
     *                             contains a ".." path-traversal segment.
     */
    public static function of(string $storageKey, ImageFormat $format, DateTimeImmutable $uploadedAt): self
    {
        if ($storageKey === '') {
            throw InvalidProfilePhoto::emptyStorageKey();
        }

        if (preg_match(self::ALLOWED_PATTERN, $storageKey) !== 1) {
            throw InvalidProfilePhoto::illegalCharacters($storageKey);
        }

        $segments = explode('/', $storageKey);
        if (in_array('..', $segments, true)) {
            throw InvalidProfilePhoto::pathTraversal($storageKey);
        }

        return new self($storageKey, $format, $uploadedAt);
    }

    public function equals(self $other): bool
    {
        return $this->storageKey === $other->storageKey
            && $this->format === $other->format
            && $this->uploadedAt == $other->uploadedAt;
    }

    /**
     * The key's basename without its extension, used as a cache-busting
     * URL segment: a new upload always allocates a fresh storage key (see
     * {@see \App\Households\Domain\MemberPhotoStorage::store()}), so the
     * version changes whenever the underlying file changes.
     */
    public function version(): string
    {
        $basename = basename($this->storageKey);
        $dot = strrpos($basename, '.');

        return $dot === false ? $basename : substr($basename, 0, $dot);
    }
}
