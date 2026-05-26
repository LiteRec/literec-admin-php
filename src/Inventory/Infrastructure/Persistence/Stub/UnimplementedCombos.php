<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Stub;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Combo;
use App\Inventory\Domain\Combos;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use LogicException;

/**
 * Fail-loud production binding for the {@see Combos} port while the
 * Doctrine adapter + migration are in flight.
 *
 * Every method throws — keeping the container compileable so handlers
 * that depend on the port can autowire, while any accidental dispatch
 * of a Combo command in production surfaces immediately instead of
 * writing to non-durable in-memory state. The real adapter lands in a
 * focused follow-up.
 */
final class UnimplementedCombos implements Combos
{
    public function add(Combo $combo): void
    {
        throw self::notImplemented();
    }

    public function save(Combo $combo): void
    {
        throw self::notImplemented();
    }

    public function byId(ComboId $id): Combo
    {
        throw self::notImplemented();
    }

    public function byListingId(ListingId $listingId): Combo
    {
        throw self::notImplemented();
    }

    public function forComponent(InventoryItemId $componentItemId): array
    {
        throw self::notImplemented();
    }

    private static function notImplemented(): LogicException
    {
        return new LogicException(
            'Combo persistence is not yet implemented in production — '
            . 'the Doctrine adapter lands in the LRA-80 follow-up. Dispatching '
            . 'a Combo command on this binding is a bug.',
        );
    }
}
