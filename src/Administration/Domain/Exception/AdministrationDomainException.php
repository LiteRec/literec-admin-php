<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use Throwable;

/**
 * Marker for every domain-level exception thrown by the Administration
 * bounded context.
 *
 * HTTP boundary listeners catch this interface (or a specific subtype) to
 * translate domain failures into stable status codes without inspecting
 * exception messages.
 */
interface AdministrationDomainException extends Throwable
{
}
