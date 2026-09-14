<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ui\Dashboard;

use App\Ui\Dashboard\SecurityCurrentStaffMember;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

#[Small]
final class SecurityCurrentStaffMemberTest extends TestCase
{
    #[Test]
    #[TestWith(['leslie.knope', 'Leslie'], 'dotted username takes the first segment')]
    #[TestWith(['dashboard_e2e', 'Dashboard'], 'underscored username takes the first segment')]
    #[TestWith(['RONSWANSON', 'Ronswanson'], 'single-word username is title-cased')]
    #[TestWith(['jane.doe@example.com', 'Jane'], 'dotted email-shaped username takes the first segment')]
    #[TestWith(['jane@example.com', 'Jane'], 'email-shaped username splits on the @ separator')]
    #[TestDox('Derives a title-cased first name from the signed-in user\'s identifier.')]
    public function derives_first_name_from_the_username(string $identifier, string $expected): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn($identifier);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        self::assertSame($expected, (new SecurityCurrentStaffMember($security))->firstName());
    }

    #[Test]
    #[TestDox('Falls back to a generic label when no user is authenticated.')]
    public function falls_back_when_no_user_is_authenticated(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        self::assertSame('there', (new SecurityCurrentStaffMember($security))->firstName());
    }
}
