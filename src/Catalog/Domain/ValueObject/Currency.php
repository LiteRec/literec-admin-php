<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * Closed set of currencies supported by the Catalog context.
 *
 * Single-currency for now; adding a new case is an additive contract
 * change that does not affect existing persisted data.
 */
enum Currency: string
{
    case USD = 'USD';
}
