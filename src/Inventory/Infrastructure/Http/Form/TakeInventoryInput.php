<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the LRA-87 "Take Inventory" bulk
 * grid. Holds one {@see TakeInventoryLineInput} per row in `lines`.
 *
 * Symfony's PropertyAccessor writes back into the `data_class` instance
 * via reflection on writable properties; the project's "immutability by
 * default" rule cannot apply here. The companion application-layer
 * command DTO ({@see \App\Inventory\Application\Command\AdjustStock})
 * stays `final readonly`. This Infrastructure-only adapter exists purely
 * to receive form input and is transposed into one AdjustStock command
 * per variance row inside the controller.
 *
 * @internal Belongs to the Inventory HTTP boundary; never referenced
 *           from Domain or Application code.
 */
final class TakeInventoryInput
{
    /** @var list<TakeInventoryLineInput> */
    public array $lines = [];
}
