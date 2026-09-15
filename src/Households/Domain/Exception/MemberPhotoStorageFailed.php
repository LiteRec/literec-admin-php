<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class MemberPhotoStorageFailed extends DomainException implements HouseholdsDomainException
{
    /**
     * @param string $storageKey Not embedded in the message — see the
     *                           codebase-wide convention of keeping
     *                           caller-controlled identifiers out of
     *                           exception text.
     */
    public static function writing(string $storageKey): self
    {
        unset($storageKey);

        return new self('The member photo could not be written to storage.');
    }

    /**
     * @param string $storageKey Not embedded in the message — see
     *                           {@see self::writing()}.
     */
    public static function notFound(string $storageKey): self
    {
        unset($storageKey);

        return new self('The member photo could not be found in storage.');
    }

    /**
     * @param string $storageKey Not embedded in the message — see
     *                           {@see self::writing()}.
     */
    public static function removing(string $storageKey): self
    {
        unset($storageKey);

        return new self('The member photo could not be removed from storage.');
    }
}
