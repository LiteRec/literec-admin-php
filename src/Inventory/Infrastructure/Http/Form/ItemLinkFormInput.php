<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the LRA-89 Link Item dialog.
 *
 * Form binding requires reflection-writable properties, so this class
 * is not `readonly` — see the rationale documented on
 * {@see InventoryItemInput}. The companion command DTO
 * ({@see \App\Inventory\Application\Command\LinkItem}) stays
 * `final readonly`.
 *
 * `includeUntilIso` is a YYYY-MM-DD ISO date string or null. The
 * controller forwards it as-is to the command DTO, which constructs a
 * {@see \DateTimeImmutable} on the handler side.
 *
 * @internal Belongs to the Inventory HTTP boundary; never referenced
 *           from Domain or Application code.
 */
final class ItemLinkFormInput
{
    public ?string $linkedItemId = null;

    public int $reservedQuantityUnits = 0;

    public bool $unlimited = false;

    public int $minRequiredUnits = 0;

    public int $maxPerPurchaseUnits = 0;

    public ?string $includeUntilIso = null;
}
