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
 * Inheritance direction: every {@see HouseholdsDomainException} IS a
 * {@see SharedDomainException}, so catching `SharedDomainException`
 * always observes Households failures. The reverse is NOT true —
 * an exception that only implements `SharedDomainException` (for
 * example, {@see App\Shared\Domain\Exception\InvalidEmailAddress}
 * raised from a shared-kernel VO call) is NOT caught by code that
 * type-hints `HouseholdsDomainException`. Catchers that need to
 * handle both should target `SharedDomainException` directly.
 */
interface HouseholdsDomainException extends SharedDomainException
{
}
