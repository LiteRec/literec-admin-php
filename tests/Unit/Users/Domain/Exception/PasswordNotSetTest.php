<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain\Exception;

use App\Users\Domain\Exception\PasswordNotSet;
use App\Users\Domain\Exception\UsersDomainException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class PasswordNotSetTest extends TestCase
{
    #[Test]
    #[TestDox('Reports a fixed persistence-guard message.')]
    public function reports_a_fixed_message(): void
    {
        $e = PasswordNotSet::throw();

        self::assertInstanceOf(UsersDomainException::class, $e);
        self::assertSame('A user cannot be persisted without a password.', $e->getMessage());
    }
}
