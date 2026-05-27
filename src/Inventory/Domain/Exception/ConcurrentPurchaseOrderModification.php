<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use Throwable;

/**
 * Raised when a PurchaseOrder save races a concurrent modification —
 * either header-level (status transition) or line-level (receive).
 * The Doctrine optimistic lock surfaces as
 * {@see \Doctrine\ORM\OptimisticLockException}; the application-side
 * handler trait wraps it as this named exception so controllers can
 * map the race to HTTP 409 without sniffing for the raw Doctrine
 * class.
 */
final class ConcurrentPurchaseOrderModification extends ConcurrentModification
{
    public static function forPurchaseOrder(PurchaseOrderId $id, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Purchase order %s was modified concurrently — reload and retry.', $id->value),
            $previous,
        );
    }

    public static function forLine(
        PurchaseOrderId $id,
        PurchaseOrderLineId $lineId,
        ?Throwable $previous = null,
    ): self {
        return new self(
            sprintf(
                'Purchase order %s line %s was modified concurrently — reload and retry.',
                $id->value,
                $lineId->value,
            ),
            $previous,
        );
    }
}
