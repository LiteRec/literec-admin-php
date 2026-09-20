<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Application;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Exception\InvalidActorState;
use App\Administration\Domain\ValueObject\ActorKind;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class ActorAssemblerTest extends TestCase
{
    private const string ADMINISTRATOR_ID = '019571bf-5d51-7000-b500-00000000ab01';
    private const string SIGN_IN_ACCOUNT_ID = '019571bf-5d51-7000-b500-00000000ac01';

    private ActorAssembler $assembler;

    protected function setUp(): void
    {
        $this->assembler = new ActorAssembler();
    }

    #[Test]
    #[TestDox('Builds an administrator actor from ActorKind::Administrator and an id.')]
    public function builds_administrator_actor(): void
    {
        $actor = $this->assembler->fromPrimitives(ActorKind::Administrator->value, self::ADMINISTRATOR_ID);

        self::assertSame(ActorKind::Administrator, $actor->kind);
        self::assertNotNull($actor->administratorId);
        self::assertSame(self::ADMINISTRATOR_ID, $actor->administratorId->value);
    }

    #[Test]
    #[TestDox('Builds a sign-in-account actor from ActorKind::SignInAccount and an id.')]
    public function builds_sign_in_account_actor(): void
    {
        $actor = $this->assembler->fromPrimitives(ActorKind::SignInAccount->value, self::SIGN_IN_ACCOUNT_ID);

        self::assertSame(ActorKind::SignInAccount, $actor->kind);
        self::assertNotNull($actor->signInAccountId);
        self::assertSame(self::SIGN_IN_ACCOUNT_ID, $actor->signInAccountId->value);
    }

    #[Test]
    #[TestDox('Builds a system actor from ActorKind::System and no id.')]
    public function builds_system_actor(): void
    {
        $actor = $this->assembler->fromPrimitives(ActorKind::System->value, null);

        self::assertSame(ActorKind::System, $actor->kind);
    }

    #[Test]
    #[TestDox('Rejects an administrator kind with no identifier.')]
    public function rejects_administrator_without_identifier(): void
    {
        $this->expectException(InvalidActorState::class);
        $this->assembler->fromPrimitives(ActorKind::Administrator->value, null);
    }

    #[Test]
    #[TestDox('Rejects a sign-in-account kind with no identifier.')]
    public function rejects_sign_in_account_without_identifier(): void
    {
        $this->expectException(InvalidActorState::class);
        $this->assembler->fromPrimitives(ActorKind::SignInAccount->value, null);
    }

    #[Test]
    #[TestDox('Rejects a system kind carrying an identifier.')]
    public function rejects_system_with_identifier(): void
    {
        $this->expectException(InvalidActorState::class);
        $this->assembler->fromPrimitives(ActorKind::System->value, self::ADMINISTRATOR_ID);
    }
}
