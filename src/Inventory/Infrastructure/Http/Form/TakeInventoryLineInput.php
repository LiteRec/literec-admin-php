<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for one row of the LRA-87 "Take
 * Inventory" bulk grid.
 *
 * Symfony's PropertyAccessor writes back into the `data_class` instance
 * via reflection on writable properties; the project's "immutability by
 * default" rule cannot apply here. The companion application-layer
 * command DTO ({@see \App\Inventory\Application\Command\AdjustStock})
 * stays `final readonly`. This Infrastructure-only adapter exists purely
 * to receive form input and is transposed into the matching command DTO
 * inside the controller.
 *
 * @internal Belongs to the Inventory HTTP boundary; never referenced
 *           from Domain or Application code.
 */
final class TakeInventoryLineInput
{
    public ?string $itemId = null;

    /** Hidden mirror of the catalogue listing code — surfaced in row-level error messages. */
    public ?string $listingCode = null;

    /** Hidden: server-recorded on-hand at render time, used to detect variance. */
    public ?int $expected = null;

    /** Operator-entered counted quantity. */
    public ?int $actual = null;

    /** Operator-chosen sub-category (StockAdjustmentReason::*). Required iff actual != expected. */
    public ?string $reason = null;

    /** Optional free-text note that rides along on the ledger row. */
    public ?string $reasonNote = null;
}
