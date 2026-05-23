<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Persistence\Doctrine\Type;

use App\Users\Domain\ValueObject\Username;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class UsernameType extends Type
{
    public const NAME = 'users_username';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 180]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Username
    {
        if ($value === null || $value instanceof Username) {
            return $value;
        }

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Expected string or Username, got %s.',
                get_debug_type($value),
            ));
        }

        return Username::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Username) {
            return $value->value;
        }

        throw new \UnexpectedValueException(sprintf(
            'Expected null or Username, got %s.',
            get_debug_type($value),
        ));
    }
}
