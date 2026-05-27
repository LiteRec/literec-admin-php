<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the LRA-89 Create / Edit Item Group
 * dialog.
 *
 * Form binding requires reflection-writable properties, so this class
 * is not `readonly` — see the rationale documented on
 * {@see InventoryItemInput}. The companion command DTOs
 * ({@see \App\Inventory\Application\Command\CreateItemGroup},
 * {@see \App\Inventory\Application\Command\RenameItemGroup},
 * {@see \App\Inventory\Application\Command\RecolorItemGroup}) stay
 * `final readonly`.
 *
 * `scope` is the string discriminator ("all" or "facility") chosen by
 * the operator; `facilityCode` is required iff scope=facility and is
 * mapped to a single-element `facilityCodes` list before dispatching
 * the {@see \App\Inventory\Application\Command\CreateItemGroup}
 * command.
 *
 * @internal Belongs to the Inventory HTTP boundary; never referenced
 *           from Domain or Application code.
 */
final class ItemGroupFormInput
{
    public ?string $name = null;

    public ?string $colorHex = '#FFFFFF';

    public string $scope = self::SCOPE_ALL;

    public ?string $facilityCode = null;

    public const string SCOPE_ALL = 'all';

    public const string SCOPE_FACILITY = 'facility';
}
