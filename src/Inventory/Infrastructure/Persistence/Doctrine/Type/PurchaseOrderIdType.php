<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class PurchaseOrderIdType extends Type
{
    public const NAME = 'inventory_purchase_order_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PurchaseOrderId
    {
        if ($value === null || $value instanceof PurchaseOrderId) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or PurchaseOrderId, got %s.',
                get_debug_type($value),
            ));
        }

        return PurchaseOrderId::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PurchaseOrderId) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or PurchaseOrderId, got %s.',
            get_debug_type($value),
        ));
    }
}
