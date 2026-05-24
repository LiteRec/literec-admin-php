<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use Throwable;

/**
 * Marker for every domain-level exception thrown by the Households
 * bounded context.
 *
 * HTTP boundary listeners catch this interface (or a specific subtype) to
 * translate domain failures into stable status codes without inspecting
 * exception messages.
 */
interface HouseholdsDomainException extends Throwable
{
}
