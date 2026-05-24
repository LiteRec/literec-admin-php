<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Type;

use App\Households\Domain\ValueObject\MemberId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class MemberIdType extends Type
{
    public const NAME = 'households_member_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?MemberId
    {
        if ($value === null || $value instanceof MemberId) {
            return $value;
        }

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Expected string or MemberId, got %s.',
                get_debug_type($value),
            ));
        }

        return MemberId::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof MemberId) {
            return $value->value;
        }

        throw new \UnexpectedValueException(sprintf(
            'Expected null or MemberId, got %s.',
            get_debug_type($value),
        ));
    }
}
