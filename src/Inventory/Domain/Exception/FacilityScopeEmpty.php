<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class FacilityScopeEmpty extends DomainException implements InventoryDomainException
{
    public static function create(): self
    {
        return new self(
            'Facility-scoped item group requires at least one facility — use FacilityScope::all() for unscoped.',
        );
    }
}
