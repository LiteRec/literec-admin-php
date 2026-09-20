<?php

declare(strict_types=1);

namespace App\Tests\Support\EventListener;

use App\Administration\Application\Security\CurrentAdministrator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Test-only instrumentation for {@see \App\Tests\Functional\Administration\RevocationTakesEffectNextRequestTest}.
 *
 * Stamps the {@see CurrentAdministrator} standing resolved DURING the
 * request that is being handled onto a response header. This exists
 * because `kernel.response` fires before `kernel.terminate` (and
 * therefore before {@see \App\Administration\Infrastructure\Security\SecurityCurrentAdministrator::reset()}
 * clears the per-request memo) — a test that instead re-queries
 * CurrentAdministrator from the container *after* `$client->request()`
 * returns is reading a value from a logically later point (post-reset),
 * which cannot distinguish a working reset() from a broken one: either
 * way, that later read recomputes fresh. Reading this header is what
 * actually pins down what application code running inside the request
 * itself would have observed.
 *
 * Registered only under `when@test` in services.yaml; never loaded in
 * dev/prod, and it never runs against a request CurrentAdministrator has
 * not already been asked about — it introduces no new resolution, only
 * observability into the existing one.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse')]
final class StampsCurrentAdministratorStandingHeader
{
    public const string HEADER = 'X-Test-Administrator-Standing';
    public const string NONE_VALUE = 'NONE';

    public function __construct(private readonly CurrentAdministrator $currentAdministrator)
    {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $standing = $this->currentAdministrator->standing();
        $value = $standing === null ? self::NONE_VALUE : $standing->standing;
        $event->getResponse()->headers->set(self::HEADER, $value);
    }
}
