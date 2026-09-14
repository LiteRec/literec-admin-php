<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidPersonName;

/**
 * Composite name of an individual member. First and last name are required;
 * middle name, suffix, and nickname are optional. All non-empty parts are
 * trimmed.
 *
 * The nickname ("Goes by") is identity data alongside the legal name parts,
 * but is deliberately excluded from {@see self::fullName()} — it is not
 * part of the member's legal name.
 */
final readonly class PersonName
{
    public string $firstName;
    public ?string $middleName;
    public string $lastName;
    public ?string $suffix;
    public ?string $nickname;

    private function __construct(
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?string $suffix,
        ?string $nickname,
    ) {
        $this->firstName = $firstName;
        $this->middleName = $middleName;
        $this->lastName = $lastName;
        $this->suffix = $suffix;
        $this->nickname = $nickname;
    }

    public static function of(
        string $firstName,
        string $lastName,
        ?string $middleName = null,
        ?string $suffix = null,
        ?string $nickname = null,
    ): self {
        $firstTrimmed = trim($firstName);

        if ($firstTrimmed === '') {
            throw InvalidPersonName::emptyFirstName();
        }

        $lastTrimmed = trim($lastName);

        if ($lastTrimmed === '') {
            throw InvalidPersonName::emptyLastName();
        }

        $middleTrimmed = $middleName !== null ? trim($middleName) : null;
        if ($middleTrimmed === '') {
            $middleTrimmed = null;
        }

        $suffixTrimmed = $suffix !== null ? trim($suffix) : null;
        if ($suffixTrimmed === '') {
            $suffixTrimmed = null;
        }

        $nicknameTrimmed = $nickname !== null ? trim($nickname) : null;
        if ($nicknameTrimmed === '') {
            $nicknameTrimmed = null;
        }

        return new self($firstTrimmed, $middleTrimmed, $lastTrimmed, $suffixTrimmed, $nicknameTrimmed);
    }

    public function fullName(): string
    {
        $parts = array_filter(
            [$this->firstName, $this->middleName, $this->lastName, $this->suffix],
            static fn(?string $p): bool => $p !== null,
        );

        return implode(' ', $parts);
    }

    public function equals(self $other): bool
    {
        return $this->firstName === $other->firstName
            && $this->middleName === $other->middleName
            && $this->lastName === $other->lastName
            && $this->suffix === $other->suffix
            && $this->nickname === $other->nickname;
    }
}
