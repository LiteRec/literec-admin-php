<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use DomainException;

final class InvalidTaxTreatment extends DomainException implements CatalogDomainException
{
    public static function includedRequiresApplied(): self
    {
        return new self(
            'A tax treatment cannot mark tax as included in the fee when no tax is applied.'
        );
    }
}
