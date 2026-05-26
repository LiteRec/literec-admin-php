<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\StockBatchId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class StockBatchIdType extends Type
{
    public const NAME = 'inventory_stock_batch_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?StockBatchId
    {
        if ($value === null || $value instanceof StockBatchId) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or StockBatchId, got %s.',
                get_debug_type($value),
            ));
        }

        return StockBatchId::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof StockBatchId) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or StockBatchId, got %s.',
            get_debug_type($value),
        ));
    }
}
