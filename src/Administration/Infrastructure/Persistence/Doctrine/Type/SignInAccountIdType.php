<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Type;

use App\Administration\Domain\ValueObject\SignInAccountId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class SignInAccountIdType extends Type
{
    public const NAME = 'administration_sign_in_account_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SignInAccountId
    {
        if ($value === null || $value instanceof SignInAccountId) {
            return $value;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or SignInAccountId, got %s.',
                get_debug_type($value),
            ));
        }

        return SignInAccountId::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SignInAccountId) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or SignInAccountId, got %s.',
            get_debug_type($value),
        ));
    }
}
