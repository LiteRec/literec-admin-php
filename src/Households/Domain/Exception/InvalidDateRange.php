<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidDateRange extends DomainException implements HouseholdsDomainException
{
    public static function endBeforeStart(): self
    {
        return new self('Date range end must not be earlier than its start.');
    }
}
