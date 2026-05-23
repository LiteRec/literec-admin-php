<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain\ValueObject;

use App\Users\Domain\Exception\InvalidHashedPassword;
use App\Users\Domain\ValueObject\HashedPassword;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class HashedPasswordTest extends TestCase
{
    #[Test]
    #[TestWith(['$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR'])]
    #[TestWith(['$argon2i$v=19$m=65536,t=4,p=1$c29tZXNhbHQ$hash'])]
    #[TestWith(['$argon2id$v=19$m=65536,t=3,p=4$c29tZXNhbHQ$hash'])]
    #[TestWith(['$2a$12$abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRS'])]
    #[TestDox('Accepts a string with a recognised password-hash prefix.')]
    public function accepts_known_password_hash_formats(string $hash): void
    {
        self::assertSame($hash, HashedPassword::fromHash($hash)->value);
    }

    #[Test]
    #[TestDox('Rejects an empty string with InvalidHashedPassword::empty().')]
    public function rejects_empty_string(): void
    {
        $this->expectException(InvalidHashedPassword::class);
        $this->expectExceptionMessage('A hashed password must not be empty.');

        HashedPassword::fromHash('');
    }

    #[Test]
    #[TestWith(['plaintext'])]
    #[TestWith(['md5deadbeef'])]
    #[TestWith(['$1$wrongtype$hash'])]
    #[TestWith(['no-dollar-prefix'])]
    #[TestDox('Rejects strings without a recognised password-hash prefix.')]
    public function rejects_unknown_or_plaintext_formats(string $value): void
    {
        $this->expectException(InvalidHashedPassword::class);
        $this->expectExceptionMessage('The provided string is not a recognized password hash.');

        HashedPassword::fromHash($value);
    }

    #[Test]
    #[TestDox('Equals another HashedPassword with the same digest.')]
    public function equals_another_hashed_password_with_the_same_digest(): void
    {
        $hash = '$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR';

        self::assertTrue(HashedPassword::fromHash($hash)->equals(HashedPassword::fromHash($hash)));
    }

    #[Test]
    #[TestDox('Differs from another HashedPassword with a different digest.')]
    public function differs_from_another_hashed_password_with_a_different_digest(): void
    {
        $a = HashedPassword::fromHash('$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR');
        $b = HashedPassword::fromHash('$2y$10$zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz0');

        self::assertFalse($a->equals($b));
    }
}
