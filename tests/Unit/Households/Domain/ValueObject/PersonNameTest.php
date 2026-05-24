<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidPersonName;
use App\Households\Domain\ValueObject\PersonName;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class PersonNameTest extends TestCase
{
    #[Test]
    #[TestDox('Constructs from first and last name with optional middle and suffix.')]
    public function constructs_with_all_parts(): void
    {
        $name = PersonName::of('Ada', 'Lovelace', 'Augusta', 'PhD');

        self::assertSame('Ada', $name->firstName);
        self::assertSame('Augusta', $name->middleName);
        self::assertSame('Lovelace', $name->lastName);
        self::assertSame('PhD', $name->suffix);
    }

    #[Test]
    #[TestDox('Trims surrounding whitespace on every part.')]
    public function trims_whitespace(): void
    {
        $name = PersonName::of('  Ada  ', '  Lovelace  ', '  Augusta  ', '  PhD  ');

        self::assertSame('Ada', $name->firstName);
        self::assertSame('Augusta', $name->middleName);
        self::assertSame('Lovelace', $name->lastName);
        self::assertSame('PhD', $name->suffix);
    }

    #[Test]
    #[TestDox('Normalizes empty-string middle name and suffix to null.')]
    public function empty_optional_parts_become_null(): void
    {
        $name = PersonName::of('Ada', 'Lovelace', '   ', '');

        self::assertNull($name->middleName);
        self::assertNull($name->suffix);
    }

    #[Test]
    #[TestDox('Rejects an empty first name with InvalidPersonName.')]
    public function rejects_empty_first_name(): void
    {
        $this->expectException(InvalidPersonName::class);

        PersonName::of('   ', 'Lovelace');
    }

    #[Test]
    #[TestDox('Rejects an empty last name with InvalidPersonName.')]
    public function rejects_empty_last_name(): void
    {
        $this->expectException(InvalidPersonName::class);

        PersonName::of('Ada', '   ');
    }

    #[Test]
    #[TestDox('fullName() joins present parts in first-middle-last-suffix order.')]
    public function full_name_joins_present_parts(): void
    {
        self::assertSame(
            'Ada Augusta Lovelace PhD',
            PersonName::of('Ada', 'Lovelace', 'Augusta', 'PhD')->fullName(),
        );
        self::assertSame(
            'Ada Lovelace',
            PersonName::of('Ada', 'Lovelace')->fullName(),
        );
        self::assertSame(
            'Ada Augusta Lovelace',
            PersonName::of('Ada', 'Lovelace', 'Augusta')->fullName(),
        );
    }

    #[Test]
    #[TestDox('Equals another PersonName with identical parts.')]
    public function equals(): void
    {
        $a = PersonName::of('Ada', 'Lovelace', 'Augusta', 'PhD');
        $b = PersonName::of('Ada', 'Lovelace', 'Augusta', 'PhD');
        $c = PersonName::of('Ada', 'Lovelace');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
