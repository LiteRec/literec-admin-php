<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain\ValueObject;

use App\Users\Domain\Exception\InvalidOneTimePassword;
use App\Users\Domain\ValueObject\OneTimePassword;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class OneTimePasswordTest extends TestCase
{
    #[Test]
    #[TestDox('Accepts a 12-character credential with no whitespace.')]
    public function accepts_a_twelve_character_credential(): void
    {
        $value = 'aB3dE6fH9jKm';

        self::assertSame($value, OneTimePassword::of($value)->value);
    }

    #[Test]
    #[TestWith(['short'], 'shorter than 12 characters')]
    #[TestWith(['waytoolongcredential'], 'longer than 12 characters')]
    #[TestWith([''], 'empty string')]
    #[TestDox('Rejects a credential whose length is not exactly 12.')]
    public function rejects_a_credential_of_the_wrong_length(string $value): void
    {
        $this->expectException(InvalidOneTimePassword::class);

        OneTimePassword::of($value);
    }

    #[Test]
    #[TestWith(["aB3dE6fH9j m"], 'internal space')]
    #[TestWith(["aB3dE6fH9jX\t"], 'trailing tab')]
    #[TestWith(["\naB3dE6fH9jX"], 'leading newline')]
    #[TestDox('Rejects a credential containing whitespace.')]
    public function rejects_a_credential_containing_whitespace(string $value): void
    {
        $this->expectException(InvalidOneTimePassword::class);
        $this->expectExceptionMessage('A one-time password must not contain whitespace.');

        OneTimePassword::of($value);
    }

    #[Test]
    #[TestDox('Equals another OneTimePassword with the same value.')]
    public function equals_another_one_time_password_with_the_same_value(): void
    {
        $a = OneTimePassword::of('aB3dE6fH9jKm');
        $b = OneTimePassword::of('aB3dE6fH9jKm');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    #[TestDox('Differs from another OneTimePassword with a different value.')]
    public function differs_from_another_one_time_password_with_a_different_value(): void
    {
        $a = OneTimePassword::of('aB3dE6fH9jKm');
        $b = OneTimePassword::of('zZ3dE6fH9jKm');

        self::assertFalse($a->equals($b));
    }
}
