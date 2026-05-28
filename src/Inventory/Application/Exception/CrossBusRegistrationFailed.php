<?php

declare(strict_types=1);

namespace App\Inventory\Application\Exception;

use RuntimeException;
use Throwable;

/**
 * Wraps any failure that escapes the LRA-98 RegisterInventoryItem
 * cross-bus orchestration — the inner Catalog RegisterListing
 * dispatch, the Inventory aggregate construction, or the repository
 * persist. The original cause is preserved via getPrevious() so the
 * LRA-86 controller can inspect it (typically a
 * DuplicateListingCode) and map it back to a field-level form
 * error.
 *
 * The doctrine_transaction middleware around the command.bus has
 * already rolled back both writes by the time this exception
 * surfaces — callers do not need to compensate.
 */
final class CrossBusRegistrationFailed extends RuntimeException
{
    public static function fromInnerFailure(Throwable $cause): self
    {
        return new self(
            sprintf(
                'RegisterInventoryItem cross-bus dispatch failed: %s',
                $cause->getMessage(),
            ),
            previous: $cause,
        );
    }

    /**
     * The inner Catalog RegisterListing dispatch completed without
     * leaving a HandledStamp on the envelope, so the new ListingId
     * cannot be recovered to bind the Inventory aggregate.
     */
    public static function missingHandledStamp(): self
    {
        return new self(
            'RegisterListing handler returned no HandledStamp — cannot continue cross-bus registration.',
        );
    }
}
