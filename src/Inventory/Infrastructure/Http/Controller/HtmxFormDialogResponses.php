<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Shared HTMX modal-form helpers for the LRA-89 Combo / Group / Link
 * dialog controllers. Each consumer declares the
 * `DIALOG_TEMPLATE` and `HX_TRIGGER_EVENT` class constants the trait
 * references via `self::` resolution; the trait is mounted at use
 * site so the constants resolve into the consuming class.
 */
trait HtmxFormDialogResponses
{
    /**
     * Messenger wraps handler exceptions in HandlerFailedException;
     * unwrap to surface the original domain exception to the caller.
     */
    private function dispatchCommandUnwrapping(object $command): mixed
    {
        try {
            return $this->dispatchCommand($command);
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function reRenderForm(
        FormInterface $form,
        string $mode,
        string $formAction,
        string $modalTitle,
        string $submitLabel,
    ): Response {
        return $this->render(
            static::DIALOG_TEMPLATE,
            [
                'form' => $form->createView(),
                'mode' => $mode,
                'formAction' => $formAction,
                'modalTitle' => $modalTitle,
                'submitLabel' => $submitLabel,
            ],
            new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY),
        );
    }

    private function savedResponse(): Response
    {
        $response = new Response('', Response::HTTP_OK);
        $response->headers->set('HX-Trigger', static::HX_TRIGGER_EVENT);
        $response->headers->set('HX-Reswap', 'none');
        return $response;
    }
}
