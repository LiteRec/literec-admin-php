<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\SeniorityLevel;
use DomainException;

final class InvalidSeniorityLevel extends DomainException implements AdministrationDomainException
{
    public static function outOfRange(int $value): self
    {
        return new self(sprintf(
            'Seniority level %d is out of range; it must be between %d and %d inclusive.',
            $value,
            SeniorityLevel::MIN,
            SeniorityLevel::MAX,
        ));
    }
}
