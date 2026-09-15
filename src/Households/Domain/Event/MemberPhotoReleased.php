<?php

declare(strict_types=1);

namespace App\Households\Domain\Event;

use DateTimeImmutable;

/**
 * Records that a stored photo file is no longer referenced by any
 * {@see \App\Households\Domain\HouseholdMember} — either because it was
 * superseded by a new upload ({@see MemberPhotoAttached}) or removed
 * ({@see MemberPhotoRemoved}) — and its storage-layer bytes may be
 * deleted. Carries only the orphaned storage key so the cleanup handler
 * ({@see \App\Households\Application\Event\ReleaseMemberPhotoFileHandler})
 * has a single unconditional action to take, regardless of which of the
 * two triggering scenarios produced it.
 */
final readonly class MemberPhotoReleased
{
    public function __construct(
        public string $storageKey,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
