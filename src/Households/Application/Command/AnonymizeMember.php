<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

/**
 * Carries no confirmation text: the typed-full-name match and
 * acknowledgement checkbox are an HTTP-boundary concern (LRA-212) and must
 * never reach the command bus.
 */
final readonly class AnonymizeMember
{
    public function __construct(
        public string $householdId,
        public string $memberId,
    ) {
    }
}
