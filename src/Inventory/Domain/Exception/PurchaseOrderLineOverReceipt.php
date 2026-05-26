<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use DomainException;

/**
 * Thrown when receiveLine() would push received units past the ordered
 * quantity for a single PO line. Carries the diagnostic payload so the
 * application service / UI can render an actionable error.
 */
final class PurchaseOrderLineOverReceipt extends DomainException implements PurchaseOrderException
{
    public readonly PurchaseOrderLineId $lineId;
    public readonly Quantity $ordered;
    public readonly Quantity $alreadyReceived;
    public readonly Quantity $attempted;

    private function __construct(
        PurchaseOrderLineId $lineId,
        Quantity $ordered,
        Quantity $alreadyReceived,
        Quantity $attempted,
    ) {
        parent::__construct(sprintf(
            'PO line %s ordered %d units, %d already received; cannot receive %d more.',
            $lineId->value,
            $ordered->units,
            $alreadyReceived->units,
            $attempted->units,
        ));

        $this->lineId = $lineId;
        $this->ordered = $ordered;
        $this->alreadyReceived = $alreadyReceived;
        $this->attempted = $attempted;
    }

    public static function for(
        PurchaseOrderLineId $lineId,
        Quantity $ordered,
        Quantity $alreadyReceived,
        Quantity $attempted,
    ): self {
        return new self($lineId, $ordered, $alreadyReceived, $attempted);
    }
}
