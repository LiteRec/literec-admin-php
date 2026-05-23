<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain\Exception;

use App\Users\Domain\Exception\UserNotFound;
use App\Users\Domain\Exception\UsersDomainException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class UserNotFoundTest extends TestCase
{
    #[Test]
    #[TestDox('::byId(string) reports the missing id in the message.')]
    public function by_id_reports_the_missing_id(): void
    {
        $e = UserNotFound::byId('019571bf-5d51-7000-b500-0123456789ab');

        self::assertInstanceOf(UsersDomainException::class, $e);
        self::assertSame('No user exists with id "019571bf-5d51-7000-b500-0123456789ab".', $e->getMessage());
    }

    #[Test]
    #[TestDox('::byUsername(string) reports the missing username in the message.')]
    public function by_username_reports_the_missing_username(): void
    {
        $e = UserNotFound::byUsername('alice');

        self::assertInstanceOf(UsersDomainException::class, $e);
        self::assertSame('No user exists with username "alice".', $e->getMessage());
    }
}
