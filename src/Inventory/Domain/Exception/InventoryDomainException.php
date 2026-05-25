<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use Throwable;

/**
 * Marker for every domain-level exception thrown by the Inventory bounded context.
 *
 * HTTP boundary listeners catch this interface (or a specific subtype) to
 * translate domain failures into stable status codes without inspecting
 * exception messages.
 */
interface InventoryDomainException extends Throwable
{
}
