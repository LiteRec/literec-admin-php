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
 * dev/prod. Note that it does NOT merely observe a resolution some other
 * caller already performed: until LRA-270's voter lands, this listener is
 * the only caller of CurrentAdministrator::standing() in the codebase, so
 * it is what drives the per-request resolution (one read-model query per
 * main-request response) throughout the test environment. That is
 * deliberate and is precisely what makes the assertion meaningful — the
 * memo it populates during request 1 is the one that must not survive
 * into request 2 — but it is instrumentation with an effect, not a
 * passive probe.
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
