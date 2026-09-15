<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Security;

use App\Users\Application\Command\ConsumeOneTimePassword;
use App\Users\Domain\ValueObject\PasswordState;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Marks a one-time password as consumed the instant its holder authenticates
 * with it, and redirects to the forced "set a new password" page instead of
 * the firewall's normal default_target_path (LRA-213). Accounts already in
 * the Established state keep the ordinary post-login redirect untouched.
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
final class ConsumeOneTimePasswordOnLogin
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof SecurityUser || $user->passwordState !== PasswordState::OneTimeIssued) {
            return;
        }

        $this->commandBus->dispatch(new ConsumeOneTimePassword($user->id));

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_password_establish')));
    }
}
