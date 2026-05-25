<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\ValueObject\ListingId;

/**
 * Domain port for generating Catalog aggregate identities.
 *
 * Implementations live in Infrastructure and produce UUID v7 values so
 * identifiers are time-ordered and safe to use as primary keys before
 * the aggregate is persisted.
 */
interface IdentityGenerator
{
    public function nextListingId(): ListingId;
}
