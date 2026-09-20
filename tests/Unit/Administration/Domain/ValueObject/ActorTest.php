<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain\ValueObject;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\SignInAccountId;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class ActorTest extends TestCase
{
    private const string ADMINISTRATOR_ID = '019571bf-5d51-7000-b500-00000000ab01';
    private const string SIGN_IN_ACCOUNT_ID = '019571bf-5d51-7000-b500-00000000ac01';

    #[Test]
    #[TestDox('administrator() carries an AdministratorId and no SignInAccountId.')]
    public function administrator_carries_only_its_own_identity(): void
    {
        $actor = Actor::administrator(AdministratorId::fromString(self::ADMINISTRATOR_ID));

        self::assertSame(ActorKind::Administrator, $actor->kind);
        self::assertNotNull($actor->administratorId);
        self::assertSame(self::ADMINISTRATOR_ID, $actor->administratorId->value);
        self::assertNull($actor->signInAccountId);
    }

    #[Test]
    #[TestDox('signInAccount() carries a SignInAccountId and no AdministratorId.')]
    public function sign_in_account_carries_only_its_own_identity(): void
    {
        $actor = Actor::signInAccount(SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_ID));

        self::assertSame(ActorKind::SignInAccount, $actor->kind);
        self::assertNotNull($actor->signInAccountId);
        self::assertSame(self::SIGN_IN_ACCOUNT_ID, $actor->signInAccountId->value);
        self::assertNull($actor->administratorId);
    }

    #[Test]
    #[TestDox('system() carries neither identity.')]
    public function system_carries_no_identity(): void
    {
        $actor = Actor::system();

        self::assertSame(ActorKind::System, $actor->kind);
        self::assertNull($actor->administratorId);
        self::assertNull($actor->signInAccountId);
    }

    #[Test]
    #[TestDox('equals() compares administrator actors by their AdministratorId.')]
    public function equals_compares_administrator_actors_by_identity(): void
    {
        $a = Actor::administrator(AdministratorId::fromString(self::ADMINISTRATOR_ID));
        $b = Actor::administrator(AdministratorId::fromString(self::ADMINISTRATOR_ID));
        $other = Actor::administrator(AdministratorId::fromString('019571bf-5d51-7000-b500-00000000ab02'));

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($other));
    }

    #[Test]
    #[TestDox('equals() compares sign-in-account actors by their SignInAccountId.')]
    public function equals_compares_sign_in_account_actors_by_identity(): void
    {
        $a = Actor::signInAccount(SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_ID));
        $b = Actor::signInAccount(SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_ID));
        $other = Actor::signInAccount(SignInAccountId::fromString('019571bf-5d51-7000-b500-00000000ac02'));

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($other));
    }

    #[Test]
    #[TestDox('equals() treats every system() actor as equal.')]
    public function equals_treats_every_system_actor_as_equal(): void
    {
        self::assertTrue(Actor::system()->equals(Actor::system()));
    }

    #[Test]
    #[TestDox('equals() returns false when the kinds differ.')]
    public function equals_returns_false_across_kinds(): void
    {
        $administrator = Actor::administrator(AdministratorId::fromString(self::ADMINISTRATOR_ID));
        $system = Actor::system();

        self::assertFalse($administrator->equals($system));
    }
}
