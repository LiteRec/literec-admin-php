<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Type;

use App\Households\Domain\ValueObject\MemberCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class MemberCodeType extends Type
{
    public const NAME = 'households_member_code';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 32]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?MemberCode
    {
        if ($value === null || $value instanceof MemberCode) {
            return $value;
        }

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Expected string or MemberCode, got %s.',
                get_debug_type($value),
            ));
        }

        return MemberCode::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof MemberCode) {
            return $value->value;
        }

        throw new \UnexpectedValueException(sprintf(
            'Expected null or MemberCode, got %s.',
            get_debug_type($value),
        ));
    }
}
