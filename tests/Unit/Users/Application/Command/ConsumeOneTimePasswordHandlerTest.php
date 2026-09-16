<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Application\Command;

use App\Tests\Support\Fake\RecordingMessageBus;
use App\Users\Application\Command\ConsumeOneTimePassword;
use App\Users\Application\Command\ConsumeOneTimePasswordHandler;
use App\Users\Domain\Event\OneTimePasswordConsumed;
use App\Users\Domain\Exception\NoOneTimePasswordToConsume;
use App\Users\Domain\Exception\UserNotFound;
use App\Users\Domain\User;
use App\Users\Domain\ValueObject\HashedPassword;
use App\Users\Domain\ValueObject\PasswordState;
use App\Users\Domain\ValueObject\Role;
use App\Users\Domain\ValueObject\Roles;
use App\Users\Domain\ValueObject\UserId;
use App\Users\Domain\ValueObject\Username;
use App\Users\Infrastructure\Persistence\InMemory\InMemoryUsers;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class ConsumeOneTimePasswordHandlerTest extends TestCase
{
    private const string USER_ID = '019571bf-5d51-7000-b500-000000000001';

    private const string UNKNOWN_ID = '019571bf-5d51-7000-b500-0000000000fe';

    private const string SAMPLE_HASH = '$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR';

    private MockClock $clock;
    private InMemoryUsers $users;
    private RecordingMessageBus $eventBus;
    private ConsumeOneTimePasswordHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $this->users = new InMemoryUsers();
        $this->eventBus = new RecordingMessageBus();
        $this->handler = new ConsumeOneTimePasswordHandler($this->users, $this->clock, $this->eventBus);
    }

    #[Test]
    #[TestDox('Moves the user to OneTimeConsumed and dispatches OneTimePasswordConsumed.')]
    public function happy_path_consumes_the_issued_credential(): void
    {
        $this->seedUserWithIssuedOtp();

        ($this->handler)(new ConsumeOneTimePassword(self::USER_ID));

        $user = $this->users->byId(UserId::fromString(self::USER_ID));
        self::assertSame(PasswordState::OneTimeConsumed, $user->credential()->state);

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(OneTimePasswordConsumed::class, $messages[0]);
    }

    #[Test]
    #[TestDox('Throws NoOneTimePasswordToConsume when the user has no issued credential.')]
    public function rejects_a_user_with_no_issued_credential(): void
    {
        $this->seedEstablishedUser();

        $this->expectException(NoOneTimePasswordToConsume::class);

        ($this->handler)(new ConsumeOneTimePassword(self::USER_ID));
    }

    #[Test]
    #[TestDox('Throws UserNotFound for an unknown userId.')]
    public function rejects_unknown_user(): void
    {
        $this->expectException(UserNotFound::class);

        ($this->handler)(new ConsumeOneTimePassword(self::UNKNOWN_ID));
    }

    private function seedUserWithIssuedOtp(): void
    {
        $user = $this->seedEstablishedUser();
        $user->issueOneTimePassword(HashedPassword::fromHash(self::SAMPLE_HASH), $this->clock);
        $user->releaseEvents();
        $this->users->save($user);
    }

    private function seedEstablishedUser(): User
    {
        $user = User::register(
            UserId::fromString(self::USER_ID),
            Username::of('alice'),
            HashedPassword::fromHash(self::SAMPLE_HASH),
            Roles::of(Role::User),
            $this->clock,
        );
        $user->releaseEvents();
        $this->users->add($user);

        return $user;
    }
}
