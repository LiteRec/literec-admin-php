<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidAdministratorId;
use Stringable;

/**
 * UUID v7 identity for an Administrator aggregate (LRA-269).
 *
 * Created by this slice under the context's merge-first rule: LRA-269
 * specifies this type but merges after LRA-268, so whichever ticket
 * merges first creates it and every later ticket reuses it verbatim.
 *
 * Validation uses a regex on the canonical RFC 4122 form so the Domain
 * layer does not import `Symfony\Component\Uid\Uuid` — that is an
 * Infrastructure concern (Deptrac enforces the boundary).
 */
final readonly class AdministratorId implements Stringable
{
    private const UUID_V7_PATTERN
        = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public string $value;

    private function __construct(string $value)
    {
        if (preg_match(self::UUID_V7_PATTERN, $value) !== 1) {
            throw InvalidAdministratorId::for($value);
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
