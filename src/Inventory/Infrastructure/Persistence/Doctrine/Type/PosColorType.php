<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\PosColor;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class PosColorType extends Type
{
    public const NAME = 'inventory_pos_color';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 7, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PosColor
    {
        if ($value === null || $value instanceof PosColor) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or PosColor, got %s.',
                get_debug_type($value),
            ));
        }

        return PosColor::ofHex($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PosColor) {
            return $value->hex;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or PosColor, got %s.',
            get_debug_type($value),
        ));
    }
}
