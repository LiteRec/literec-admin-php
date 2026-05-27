<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the LRA-87 "Receive Stock" HTMX dialog.
 *
 * Symfony's PropertyAccessor writes back into the `data_class` instance
 * via reflection on writable properties; that prevents the project's
 * "immutability by default" rule from applying here. The companion
 * application-layer command DTO
 * ({@see \App\Inventory\Application\Command\ReceiveStockManually})
 * stays `final readonly`. This Infrastructure-only adapter exists purely
 * to receive form input and is then transposed into the matching command
 * DTO inside the controller.
 *
 * @internal Belongs to the Inventory HTTP boundary; never referenced
 *           from Domain or Application code.
 */
final class ReceiveStockInput
{
    public ?string $facilityCode = null;

    public ?int $quantityUnits = null;

    /**
     * Per-unit acquisition cost in integer cents. Mutually exclusive with
     * {@see self::$totalCostCents} — the controller normalises whichever
     * is supplied into the {@see \App\Inventory\Application\Command\ReceiveStockManually}
     * primitive contract.
     */
    public ?int $costPerUnitCents = null;

    /**
     * Total receipt cost in integer cents. The controller divides by
     * quantity (intdiv) to derive the per-unit value the command DTO
     * actually carries; any positive remainder is recorded onto the
     * comment so no money is silently dropped.
     */
    public ?int $totalCostCents = null;

    public ?string $comment = null;
}
