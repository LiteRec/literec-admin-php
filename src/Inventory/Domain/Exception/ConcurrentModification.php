<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * Sealed base for the LRA-99 named concurrency exceptions. Callers
 * can catch the abstract type to react uniformly (e.g. translate any
 * concurrent-modification race to HTTP 409) without inspecting the
 * specific aggregate.
 */
abstract class ConcurrentModification extends RuntimeException implements InventoryDomainException
{
    final protected function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
