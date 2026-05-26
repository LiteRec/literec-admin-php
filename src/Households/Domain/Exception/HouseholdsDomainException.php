<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Shared\Domain\Exception\SharedDomainException;

/**
 * Marker for every domain-level exception thrown by the Households
 * bounded context.
 *
 * HTTP boundary listeners catch this interface (or a specific subtype) to
 * translate domain failures into stable status codes without inspecting
 * exception messages.
 *
 * Extends {@see SharedDomainException} so listeners that catch the
 * per-context marker still observe invalid-VO failures raised from
 * shared-kernel value objects when they bubble up through Households
 * code paths.
 */
interface HouseholdsDomainException extends SharedDomainException
{
}
