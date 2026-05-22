<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testConstructorRejectsAnEmptyUsername(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User('');
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        self::assertContains('ROLE_USER', (new User('alice'))->getRoles());
    }

    public function testGetRolesIncludesAssignedRoles(): void
    {
        $user = new User('alice');
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();

        self::assertContains('ROLE_ADMIN', $roles);
        self::assertContains('ROLE_USER', $roles);
    }

    public function testGetRolesDeduplicatesRoles(): void
    {
        $user = new User('alice');
        $user->setRoles(['ROLE_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);

        self::assertCount(2, $user->getRoles());
    }

    public function testUserIdentifierIsTheUsername(): void
    {
        self::assertSame('jdoe', (new User('jdoe'))->getUserIdentifier());
    }

    public function testGetUsernameReturnsTheUsername(): void
    {
        self::assertSame('jdoe', (new User('jdoe'))->getUsername());
    }

    public function testSetPasswordUpdatesTheStoredHash(): void
    {
        $user = new User('jdoe');
        $user->setPassword('a-hashed-value');

        self::assertSame('a-hashed-value', $user->getPassword());
    }

    public function testNewUserIsActiveByDefault(): void
    {
        self::assertTrue((new User('alice'))->isActive());
    }

    public function testSetIsActiveTogglesTheFlag(): void
    {
        $user = new User('alice');
        $user->setIsActive(false);

        self::assertFalse($user->isActive());
    }

    public function testAssertPasswordIsSetThrowsWhenPasswordIsMissing(): void
    {
        $this->expectException(\LogicException::class);

        (new User('alice'))->assertPasswordIsSet();
    }

    public function testAssertPasswordIsSetPassesWhenPasswordIsSet(): void
    {
        $this->expectNotToPerformAssertions();

        $user = new User('alice');
        $user->setPassword('a-hashed-value');
        $user->assertPasswordIsSet();
    }

    public function testCreatedAtIsInitialisedToNow(): void
    {
        $before = new \DateTimeImmutable();
        $createdAt = (new User('alice'))->getCreatedAt();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $createdAt);
        self::assertLessThanOrEqual($after, $createdAt);
    }
}
