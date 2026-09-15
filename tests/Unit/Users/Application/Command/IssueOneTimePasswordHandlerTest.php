<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Application\Command;

use App\Tests\Support\Fake\FixedOneTimePasswordGenerator;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Users\Application\Command\IssueOneTimePassword;
use App\Users\Application\Command\IssueOneTimePasswordHandler;
use App\Users\Domain\Event\OneTimePasswordIssued;
use App\Users\Domain\Exception\OneTimePasswordNotAllowed;
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
final class IssueOneTimePasswordHandlerTest extends TestCase
{
    private const string USER_ID = '019571bf-5d51-7000-b500-000000000001';

    private const string UNKNOWN_ID = '019571bf-5d51-7000-b500-0000000000fe';

    private MockClock $clock;
    private InMemoryUsers $users;
    private RecordingMessageBus $eventBus;
    private IssueOneTimePasswordHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $this->users = new InMemoryUsers();
        $this->eventBus = new RecordingMessageBus();

        $this->handler = new IssueOneTimePasswordHandler(
            $this->users,
            new FixedOneTimePasswordGenerator(),
            $this->clock,
            new PasswordHasherFactory(['users' => ['algorithm' => 'bcrypt', 'cost' => 4]]),
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Returns the generated plaintext and dispatches OneTimePasswordIssued.')]
    public function happy_path_returns_plaintext_and_dispatches_event(): void
    {
        $this->seedActiveUser();

        $otp = ($this->handler)(new IssueOneTimePassword(self::USER_ID));

        self::assertSame('aB3dE6fH9jKm', $otp->value);

        $user = $this->users->byId(UserId::fromString(self::USER_ID));
        self::assertSame(PasswordState::OneTimeIssued, $user->passwordState());
        self::assertNotSame('aB3dE6fH9jKm', $user->passwordHash()->value);

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(OneTimePasswordIssued::class, $messages[0]);
    }

    #[Test]
    #[TestDox('Throws OneTimePasswordNotAllowed for a deactivated user.')]
    public function rejects_inactive_user(): void
    {
        $user = $this->seedActiveUser();
        $user->deactivate('superseded', $this->clock);
        $this->users->save($user);

        $this->expectException(OneTimePasswordNotAllowed::class);

        ($this->handler)(new IssueOneTimePassword(self::USER_ID));
    }

    #[Test]
    #[TestDox('Throws UserNotFound for an unknown userId.')]
    public function rejects_unknown_user(): void
    {
        $this->expectException(UserNotFound::class);

        ($this->handler)(new IssueOneTimePassword(self::UNKNOWN_ID));
    }

    private function seedActiveUser(): User
    {
        $user = User::register(
            UserId::fromString(self::USER_ID),
            Username::of('alice'),
            HashedPassword::fromHash('$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR'),
            [Role::User],
            $this->clock,
        );
        $user->releaseEvents();
        $this->users->add($user);

        return $user;
    }
}
