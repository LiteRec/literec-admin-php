<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain\Exception;

use App\Users\Domain\Exception\InvalidUserId;
use App\Users\Domain\Exception\UsersDomainException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class InvalidUserIdTest extends TestCase
{
    #[Test]
    #[TestDox('::for(string) reports the offending value as the message.')]
    public function reports_the_offending_value(): void
    {
        $e = InvalidUserId::for('not-a-uuid');

        self::assertInstanceOf(UsersDomainException::class, $e);
        self::assertSame('"not-a-uuid" is not a valid UUID v7.', $e->getMessage());
    }
}
