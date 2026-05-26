<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use Throwable;

/**
 * Universal marker for every domain-level exception thrown by the shared
 * kernel.
 *
 * Per-context domain exception markers (HouseholdsDomainException,
 * InventoryDomainException, ...) extend this interface so HTTP boundary
 * listeners that catch a context-scoped marker still see invalid-VO
 * failures raised from shared-kernel value objects, and a single
 * catch-all listener can target SharedDomainException directly.
 */
interface SharedDomainException extends Throwable
{
}
