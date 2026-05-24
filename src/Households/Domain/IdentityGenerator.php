<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;

/**
 * Domain port for generating Households aggregate and child entity
 * identities.
 */
interface IdentityGenerator
{
    public function nextHouseholdId(): HouseholdId;

    public function nextMemberId(): MemberId;
}
