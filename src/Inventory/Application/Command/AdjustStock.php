<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for the AdjustStock use case (Take Inventory
 * bulk count).
 *
 * Bulk-take is the UI primitive (LRA-87); the handler accepts one tuple
 * per invocation. The UI dispatches N commands inside a single HTTP
 * request — each command its own transaction, so partial failures of
 * one item do not roll back the others.
 */
final readonly class AdjustStock
{
    public function __construct(
        public string $itemId,
        public string $facilityCode,
        public int $targetQuantityUnits,
        public string $reason,
    ) {
    }
}
