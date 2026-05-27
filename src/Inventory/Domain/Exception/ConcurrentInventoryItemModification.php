<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use Throwable;

/**
 * Raised when an InventoryItem save races a concurrent modification
 * — typically two operators receiving / adjusting / transferring
 * stock for the same item at the same facility. The Doctrine
 * optimistic lock surfaces as
 * {@see \Doctrine\ORM\OptimisticLockException}; the Doctrine
 * repository adapter wraps it as this named exception so controllers can
 * map the race to HTTP 409 without sniffing for the raw Doctrine
 * class.
 */
final class ConcurrentInventoryItemModification extends ConcurrentModification
{
    public static function forItem(InventoryItemId $id, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Inventory item %s was modified concurrently — reload and retry.', $id->value),
            $previous,
        );
    }
}
