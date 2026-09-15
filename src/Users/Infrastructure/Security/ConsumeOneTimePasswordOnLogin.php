<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Security;

use App\Users\Application\Command\ConsumeOneTimePassword;
use App\Users\Domain\ValueObject\PasswordState;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Throwable;

/**
 * Marks a one-time password as consumed the instant its holder authenticates
 * with it, and redirects to the forced "set a new password" page instead of
 * the firewall's normal default_target_path (LRA-213). Accounts already in
 * the Established state keep the ordinary post-login redirect untouched.
 *
 * Consumption can lose a race (two logins with the same one-time password
 * both reach here; only one dispatched ConsumeOneTimePassword succeeds
 * against the aggregate's optimistic-lock version — see
 * {@see \App\Users\Infrastructure\Persistence\Doctrine\DoctrineUsers}) or
 * fail for any other reason. Either way the security token has already
 * been stored by the authenticator before this listener runs, so a failed
 * consumption must clear it here — otherwise the loser of the race ends
 * up with an authenticated session despite never having a valid,
 * unconsumed credential.
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
final class ConsumeOneTimePasswordOnLogin
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof SecurityUser || $user->passwordState !== PasswordState::OneTimeIssued) {
            return;
        }

        try {
            $this->commandBus->dispatch(new ConsumeOneTimePassword($user->id));
        } catch (Throwable) {
            $this->tokenStorage->setToken(null);
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));

            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_password_establish')));
    }
}
