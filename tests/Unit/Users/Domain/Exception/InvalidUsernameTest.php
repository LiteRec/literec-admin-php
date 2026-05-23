<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain\Exception;

use App\Users\Domain\Exception\InvalidUsername;
use App\Users\Domain\Exception\UsersDomainException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class InvalidUsernameTest extends TestCase
{
    #[Test]
    #[TestDox('::empty() reports an empty-username message.')]
    public function empty_reports_an_empty_username_message(): void
    {
        $e = InvalidUsername::empty();

        self::assertInstanceOf(UsersDomainException::class, $e);
        self::assertSame('A user must have a non-empty username.', $e->getMessage());
    }

    #[Test]
    #[TestDox('::tooLong(int) reports the offending length and the 180-character limit.')]
    public function too_long_reports_the_offending_length(): void
    {
        $e = InvalidUsername::tooLong(250);

        self::assertInstanceOf(UsersDomainException::class, $e);
        self::assertSame('Username length 250 exceeds the maximum of 180 characters.', $e->getMessage());
    }
}
