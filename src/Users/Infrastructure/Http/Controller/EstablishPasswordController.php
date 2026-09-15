<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Http\Controller;

use App\Users\Application\Command\EstablishPassword;
use App\Users\Infrastructure\Http\Form\EstablishPasswordFormType;
use App\Users\Infrastructure\Http\Form\EstablishPasswordInput;
use App\Users\Infrastructure\Security\SecurityUser;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * The forced "set a new password" page a one-time-password holder lands on
 * after signing in (LRA-213). Reachable by any authenticated user (not just
 * ones with a one-time password pending) so it also serves as the future
 * voluntary "change my password" entry point.
 */
final class EstablishPasswordController extends AbstractController
{
    private const string GENERIC_FAILURE = 'Unable to set the new password. Please try again.';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly Security $security,
    ) {
    }

    #[Route('/account/password', name: 'app_password_establish', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $form = $this->createForm(EstablishPasswordFormType::class, new EstablishPasswordInput());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $response = $this->handleValid($form->getData());
            if ($response !== null) {
                return $response;
            }
        }

        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render(
            'security/establish_password.html.twig',
            ['form' => $form->createView()],
            new Response(null, $status),
        );
    }

    private function handleValid(EstablishPasswordInput $input): ?Response
    {
        $user = $this->getUser();
        if (!$user instanceof SecurityUser) {
            throw new LogicException('EstablishPasswordController reached without an authenticated SecurityUser.');
        }

        try {
            $this->dispatchUnwrapping(new EstablishPassword(
                userId: $user->id,
                plaintextPassword: (string) $input->newPassword,
            ));
        } catch (Throwable) {
            $this->addFlash('error', self::GENERIC_FAILURE);

            return null;
        }

        // The hash change deauthenticates the session via SecurityUser's
        // EquatableInterface anyway; logging out explicitly makes that
        // immediate and sends the user back through a clean sign-in with
        // the new password rather than relying on lazy token refresh.
        $this->security->logout(false);
        $this->addFlash('success', 'Your password has been updated. Sign in with your new password.');

        return new RedirectResponse($this->generateUrl('app_login'));
    }

    /**
     * Messenger wraps handler exceptions in HandlerFailedException; unwrap
     * to surface the original exception to the caller.
     */
    private function dispatchUnwrapping(object $command): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }
    }
}
