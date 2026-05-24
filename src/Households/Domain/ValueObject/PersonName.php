<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidPersonName;

/**
 * Composite name of an individual member. First and last name are required;
 * middle name and suffix are optional. All non-empty parts are trimmed.
 */
final readonly class PersonName
{
    public string $firstName;
    public ?string $middleName;
    public string $lastName;
    public ?string $suffix;

    private function __construct(
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?string $suffix,
    ) {
        $this->firstName = $firstName;
        $this->middleName = $middleName;
        $this->lastName = $lastName;
        $this->suffix = $suffix;
    }

    public static function of(
        string $firstName,
        string $lastName,
        ?string $middleName = null,
        ?string $suffix = null,
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

        return new self($firstTrimmed, $middleTrimmed, $lastTrimmed, $suffixTrimmed);
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
            && $this->suffix === $other->suffix;
    }
}
