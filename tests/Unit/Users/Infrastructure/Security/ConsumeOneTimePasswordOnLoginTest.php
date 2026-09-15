<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Infrastructure\Security;

use App\Tests\Support\Fake\RecordingMessageBus;
use App\Users\Application\Command\ConsumeOneTimePassword;
use App\Users\Domain\Exception\ConcurrentUserModification;
use App\Users\Domain\ValueObject\PasswordState;
use App\Users\Infrastructure\Security\ConsumeOneTimePasswordOnLogin;
use App\Users\Infrastructure\Security\SecurityUser;
use LogicException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Covers the failure branch that cannot be exercised through a sequential
 * functional test: two logins racing to consume the same one-time password
 * only ever produce one winner in a single-threaded test client, but the
 * *effect* of losing that race — ConsumeOneTimePassword dispatch failing
 * after the security token has already been stored — is reproducible in
 * isolation by making the command bus throw (LRA-213 review round).
 */
#[Small]
final class ConsumeOneTimePasswordOnLoginTest extends TestCase
{
    private const string SAMPLE_HASH = '$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR';

    #[Test]
    #[TestDox('Dispatches ConsumeOneTimePassword and redirects to the establish-password page on success.')]
    public function redirects_to_establish_password_on_success(): void
    {
        $bus = new RecordingMessageBus();
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($this->createStub(TokenInterface::class));
        $listener = new ConsumeOneTimePasswordOnLogin($bus, $this->urlGenerator(), $tokenStorage);

        $event = $this->loginSuccessEvent(PasswordState::OneTimeIssued);
        ($listener)($event);

        self::assertCount(1, $bus->dispatchedMessages());
        self::assertInstanceOf(ConsumeOneTimePassword::class, $bus->dispatchedMessages()[0]);
        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
        self::assertSame('/account/password', $event->getResponse()->getTargetUrl());
        self::assertNotNull($tokenStorage->getToken());
    }

    #[Test]
    #[TestDox('Clears the security token and redirects to /login when consumption loses the race.')]
    public function clears_the_token_and_redirects_to_login_when_consumption_fails(): void
    {
        $bus = $this->throwingBus(new HandlerFailedException(
            new Envelope(new ConsumeOneTimePassword('019571bf-5d51-7000-b500-000000000001')),
            [ConcurrentUserModification::forUser('019571bf-5d51-7000-b500-000000000001')],
        ));
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($this->createStub(TokenInterface::class));
        $listener = new ConsumeOneTimePasswordOnLogin($bus, $this->urlGenerator(), $tokenStorage);

        $event = $this->loginSuccessEvent(PasswordState::OneTimeIssued);
        ($listener)($event);

        self::assertNull($tokenStorage->getToken());
        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
        self::assertSame('/login', $event->getResponse()->getTargetUrl());
    }

    #[Test]
    #[TestDox('Leaves an Established login untouched.')]
    public function leaves_an_established_login_untouched(): void
    {
        $bus = new RecordingMessageBus();
        $tokenStorage = new TokenStorage();
        $listener = new ConsumeOneTimePasswordOnLogin($bus, $this->urlGenerator(), $tokenStorage);

        $event = $this->loginSuccessEvent(PasswordState::Established);
        ($listener)($event);

        self::assertSame([], $bus->dispatchedMessages());
        self::assertNull($event->getResponse());
    }

    private function loginSuccessEvent(PasswordState $state): LoginSuccessEvent
    {
        $user = new SecurityUser(
            id: '019571bf-5d51-7000-b500-000000000001',
            username: 'alice',
            hashedPassword: self::SAMPLE_HASH,
            roles: ['ROLE_USER'],
            isActive: true,
            passwordState: $state,
        );
        $passport = new SelfValidatingPassport(new UserBadge('alice', static fn (): SecurityUser => $user));

        return new LoginSuccessEvent(
            $this->createStub(AuthenticatorInterface::class),
            $passport,
            $this->createStub(TokenInterface::class),
            Request::create('/login', 'POST'),
            null,
            'main',
        );
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        $generator = $this->createStub(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(
            static fn (string $name): string => match ($name) {
                'app_password_establish' => '/account/password',
                'app_login' => '/login',
                default => throw new LogicException("Unexpected route \"{$name}\"."),
            },
        );

        return $generator;
    }

    private function throwingBus(HandlerFailedException $exception): MessageBusInterface
    {
        return new class ($exception) implements MessageBusInterface {
            public function __construct(private readonly HandlerFailedException $exception)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw $this->exception;
            }
        };
    }
}
