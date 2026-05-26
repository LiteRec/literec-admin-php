<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\FacilityCode;
use DomainException;

final class CannotTransferToSameFacility extends DomainException implements InventoryDomainException
{
    public static function for(FacilityCode $facility): self
    {
        return new self(sprintf(
            'Cannot transfer stock from facility %s to itself.',
            $facility->value,
        ));
    }
}
