<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain\Exception;

use App\Users\Domain\Exception\InvalidHashedPassword;
use App\Users\Domain\Exception\UsersDomainException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class InvalidHashedPasswordTest extends TestCase
{
    #[Test]
    #[TestDox('::empty() reports an empty-hash message.')]
    public function empty_reports_an_empty_hash_message(): void
    {
        $e = InvalidHashedPassword::empty();

        self::assertInstanceOf(UsersDomainException::class, $e);
        self::assertSame('A hashed password must not be empty.', $e->getMessage());
    }

    #[Test]
    #[TestDox('::format() reports an unrecognized-hash-format message.')]
    public function format_reports_an_unrecognized_format_message(): void
    {
        $e = InvalidHashedPassword::format();

        self::assertInstanceOf(UsersDomainException::class, $e);
        self::assertSame('The provided string is not a recognized password hash.', $e->getMessage());
    }
}
