<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use DomainException;

final class InvalidMoney extends DomainException implements CatalogDomainException
{
    public static function negativeAmount(int $cents): self
    {
        return new self(sprintf('Monetary amounts must be zero or positive; got %d cents.', $cents));
    }
}
