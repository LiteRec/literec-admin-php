<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Shared helper for Inventory HTTP controllers that dispatch commands
 * through `HandleTrait::handle`. Messenger wraps handler exceptions in
 * `HandlerFailedException`; unwrap once so the controller's catch
 * blocks see the original domain exception type.
 *
 * Consumers must compose `HandleTrait` and alias the protected
 * `handle()` method to `dispatchCommand` (matching the LRA-87/89
 * convention). The trait is intentionally tiny — the LRA-89
 * {@see HtmxFormDialogResponses} trait is the right base for HTMX
 * modal-form controllers; this trait is for the LRA-90 lifecycle
 * controllers that are not modal-shaped.
 */
trait DispatchesCommandsUnwrapping
{
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
}
