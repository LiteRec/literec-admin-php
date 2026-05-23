<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain\ValueObject;

use App\Users\Domain\Exception\InvalidUsername;
use App\Users\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class UsernameTest extends TestCase
{
    #[Test]
    #[TestWith(['alice'])]
    #[TestWith(['user.name+tag'])]
    #[TestWith(['héllo'])]
    #[TestDox('Accepts a valid username and stores the trimmed value.')]
    public function accepts_a_valid_username_and_stores_the_trimmed_value(string $input): void
    {
        self::assertSame($input, Username::of($input)->value);
    }

    #[Test]
    #[TestDox('Trims surrounding whitespace before storing.')]
    public function trims_surrounding_whitespace(): void
    {
        self::assertSame('alice', Username::of('   alice   ')->value);
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(["\t\n"])]
    #[TestDox('Rejects an empty or whitespace-only username with InvalidUsername.')]
    public function rejects_empty_or_whitespace_only_usernames(string $input): void
    {
        $this->expectException(InvalidUsername::class);

        Username::of($input);
    }

    #[Test]
    #[TestDox('Rejects usernames longer than the 180-character limit.')]
    public function rejects_overlong_usernames(): void
    {
        $this->expectException(InvalidUsername::class);

        Username::of(str_repeat('a', 181));
    }

    #[Test]
    #[TestDox('Accepts a username of exactly the maximum length.')]
    public function accepts_a_username_of_exactly_the_maximum_length(): void
    {
        $value = str_repeat('a', 180);

        self::assertSame($value, Username::of($value)->value);
    }

    #[Test]
    #[TestDox('Equals another Username with the same trimmed value.')]
    public function equals_a_username_with_the_same_trimmed_value(): void
    {
        self::assertTrue(Username::of(' alice ')->equals(Username::of('alice')));
        self::assertFalse(Username::of('alice')->equals(Username::of('bob')));
    }

    #[Test]
    #[TestDox('Stringifies to the stored value.')]
    public function stringifies_to_the_stored_value(): void
    {
        self::assertSame('alice', (string) Username::of('alice'));
    }
}
