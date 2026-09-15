<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Application\Command;

use App\Tests\Support\Fake\RecordingMessageBus;
use App\Users\Application\Command\EstablishPassword;
use App\Users\Application\Command\EstablishPasswordHandler;
use App\Users\Domain\Event\PasswordChanged;
use App\Users\Domain\Exception\UserNotFound;
use App\Users\Domain\User;
use App\Users\Domain\ValueObject\HashedPassword;
use App\Users\Domain\ValueObject\PasswordState;
use App\Users\Domain\ValueObject\Role;
use App\Users\Domain\ValueObject\UserId;
use App\Users\Domain\ValueObject\Username;
use App\Users\Infrastructure\Persistence\InMemory\InMemoryUsers;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

#[Small]
final class EstablishPasswordHandlerTest extends TestCase
{
    private const string USER_ID = '019571bf-5d51-7000-b500-000000000001';

    private const string UNKNOWN_ID = '019571bf-5d51-7000-b500-0000000000fe';

    private const string SAMPLE_HASH = '$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR';

    private MockClock $clock;
    private InMemoryUsers $users;
    private RecordingMessageBus $eventBus;
    private EstablishPasswordHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $this->users = new InMemoryUsers();
        $this->eventBus = new RecordingMessageBus();

        $this->handler = new EstablishPasswordHandler(
            $this->users,
            $this->clock,
            new PasswordHasherFactory(['users' => ['algorithm' => 'bcrypt', 'cost' => 4]]),
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Returns the account to Established and dispatches PasswordChanged.')]
    public function happy_path_returns_to_established(): void
    {
        $this->seedUserWithIssuedOtp();

        ($this->handler)(new EstablishPassword(self::USER_ID, 'a-brand-new-password'));

        $user = $this->users->byId(UserId::fromString(self::USER_ID));
        self::assertSame(PasswordState::Established, $user->passwordState());
        self::assertNotSame(self::SAMPLE_HASH, $user->passwordHash()->value);

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(PasswordChanged::class, $messages[0]);
    }

    #[Test]
    #[TestDox('Throws UserNotFound for an unknown userId.')]
    public function rejects_unknown_user(): void
    {
        $this->expectException(UserNotFound::class);

        ($this->handler)(new EstablishPassword(self::UNKNOWN_ID, 'a-brand-new-password'));
    }

    private function seedUserWithIssuedOtp(): void
    {
        $user = User::register(
            UserId::fromString(self::USER_ID),
            Username::of('alice'),
            HashedPassword::fromHash(self::SAMPLE_HASH),
            [Role::User],
            $this->clock,
        );
        $user->issueOneTimePassword(HashedPassword::fromHash(self::SAMPLE_HASH), $this->clock);
        $user->releaseEvents();
        $this->users->add($user);
    }
}
