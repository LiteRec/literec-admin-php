<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dev;

use App\Controller\DevComponentsController;
use App\Controller\DevComponentsDialogController;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Guarantees the dev-only Organic component showcase (LRA-185) is not
 * reachable in production.
 *
 * `#[When(env: 'dev')]` / `#[When(env: 'test')]` on DevComponentsController
 * and DevComponentsDialogController mean Symfony skips their service (and
 * `#[Route]`) definitions entirely when compiling the container for `prod`,
 * so this asserts the service is absent from the prod-compiled container
 * directly — the exact mechanism the docblock on both controllers relies on.
 *
 * A WebTestCase HTTP round trip can't exercise this: `createClient(['environment'
 * => 'prod'])` throws because `framework.test: true` is only configured
 * `when@test` (config/packages/framework.yaml), which `createClient()` and
 * `self::getContainer()` both require regardless of the `environment` option
 * passed to them. Querying `$kernel->getContainer()->has(...)` sidesteps that
 * — `has()` doesn't need the private-service access `test.service_container`
 * exists to unlock, it just reports whether the definition was compiled in.
 */
#[Medium]
final class DevComponentsRoutesProdTest extends KernelTestCase
{
    #[Test]
    #[TestDox('The dev/components controllers are not registered when the container is compiled for prod.')]
    public function dev_components_services_are_absent_in_prod(): void
    {
        $container = self::bootKernel(['environment' => 'prod', 'debug' => false])->getContainer();

        // PHPStan's Symfony container reflection is built from one compiled
        // container dump (dev/test), so it treats has() on these ids as
        // statically `true` and flags the assertion below as always-false —
        // it can't know this test recompiles the container for `prod`, where
        // the #[When]-gated definitions are genuinely absent at runtime.
        // @phpstan-ignore staticMethod.impossibleType
        self::assertFalse(
            $container->has(DevComponentsController::class),
            'DevComponentsController must not be registered under prod.',
        );
        // @phpstan-ignore staticMethod.impossibleType
        self::assertFalse(
            $container->has(DevComponentsDialogController::class),
            'DevComponentsDialogController must not be registered under prod.',
        );
    }
}
