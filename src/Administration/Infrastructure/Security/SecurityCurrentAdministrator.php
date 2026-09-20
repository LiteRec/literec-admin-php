<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Security;

use App\Administration\Application\Query\Port\AdministratorStandingReadModel;
use App\Administration\Application\Query\View\AdministratorStandingView;
use App\Administration\Application\Security\CurrentAdministrator;
use App\Administration\Domain\ValueObject\SignInAccountId;
use App\Users\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Adapts Symfony Security to {@see CurrentAdministrator}. Reads the
 * authenticated token's {@see SecurityUser}, translating its id into a
 * {@see SignInAccountId} at this Infrastructure boundary — Domain never
 * imports App\Users (see SignInAccountId's own docblock) — then resolves
 * the standing view through {@see AdministratorStandingReadModel}, the
 * one path this question is ever answered through.
 *
 * $resolved/$memo hold the answer for the lifetime of THIS instance only.
 * Under classic per-request PHP (php-fpm, or `frankenphp` run without
 * worker mode) that is already scoped to one request, since a fresh
 * process — and therefore a fresh instance — follows every request
 * regardless. Under FrankenPHP worker mode, though, this service can
 * survive across many requests inside one long-lived process, so
 * {@see self::reset()} is required to clear the memo: it is called
 * automatically on every `kernel.terminate` because this class
 * implements {@see ResetInterface} and `services.yaml`'s
 * `_defaults.autoconfigure: true` tags any such class `kernel.reset`.
 * This reset boundary is precisely what makes revoking an administrator
 * take effect starting with the very next request rather than the
 * current instance persisting a stale "Active" answer — see
 * RevocationTakesEffectNextRequestTest, which proves it by forcing the
 * kernel/container to survive across two real HTTP requests
 * (`$client->disableReboot()`) and asserting the second one no longer
 * sees the first request's answer.
 */
final class SecurityCurrentAdministrator implements CurrentAdministrator, ResetInterface
{
    private bool $resolved = false;
    private ?AdministratorStandingView $memo = null;

    public function __construct(
        private readonly Security $security,
        private readonly AdministratorStandingReadModel $standingReadModel,
    ) {
    }

    public function standing(): ?AdministratorStandingView
    {
        if (!$this->resolved) {
            $signInAccountId = $this->currentSignInAccountId();
            $this->memo = $signInAccountId === null ? null : $this->standingReadModel->standingFor($signInAccountId);
            $this->resolved = true;
        }

        return $this->memo;
    }

    public function reset(): void
    {
        $this->resolved = false;
        $this->memo = null;
    }

    private function currentSignInAccountId(): ?SignInAccountId
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser) {
            return null;
        }

        return SignInAccountId::fromString($user->id);
    }
}
