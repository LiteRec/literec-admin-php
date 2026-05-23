<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain\Exception;

use App\Users\Domain\Exception\UsernameAlreadyTaken;
use App\Users\Domain\Exception\UsersDomainException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class UsernameAlreadyTakenTest extends TestCase
{
    #[Test]
    #[TestDox('::for(string) embeds the username in a human-readable message.')]
    public function reports_the_username_in_the_message(): void
    {
        $e = UsernameAlreadyTaken::for('alice');

        self::assertInstanceOf(UsersDomainException::class, $e);
        self::assertSame('Username "alice" is already taken.', $e->getMessage());
    }
}
