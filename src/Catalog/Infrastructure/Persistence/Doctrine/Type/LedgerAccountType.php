<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\Exception\InvalidLedgerAccount;
use App\Catalog\Domain\ValueObject\LedgerAccount;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class LedgerAccountType extends Type
{
    public const NAME = 'catalog_ledger_account';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => InvalidLedgerAccount::MAX_LENGTH]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?LedgerAccount
    {
        if ($value === null || $value instanceof LedgerAccount) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or LedgerAccount, got %s.',
                get_debug_type($value),
            ));
        }

        return LedgerAccount::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof LedgerAccount) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or LedgerAccount, got %s.',
            get_debug_type($value),
        ));
    }
}
