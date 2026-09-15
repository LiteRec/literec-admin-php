<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Forces any request from an account whose password must be replaced
 * (a one-time password not yet exchanged for a real one) onto the
 * "set a new password" page, regardless of which route it tried to reach
 * (LRA-213).
 *
 * Priority 4 runs after the Firewall listener (priority 8), so the
 * security token is already populated on the request; the dev-tooling
 * paths (_profiler, _wdt, assets) carry no token and fall through
 * unaffected because they sit on the `security: false` dev firewall.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
final class RequirePasswordEstablishmentListener
{
    private const string ESTABLISH_ROUTE = 'app_password_establish';

    private const string LOGOUT_ROUTE = 'app_logout';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof SecurityUser || !$user->passwordState->mustBeReplaced()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if ($route === self::ESTABLISH_ROUTE || $route === self::LOGOUT_ROUTE) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate(self::ESTABLISH_ROUTE),
            303,
        ));
    }
}
