<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Security;

use App\Administration\Domain\EffectivePrivileges;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\PrivilegeGrants;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Production {@see EffectivePrivileges} binding: decorates
 * {@see UnionOfGrantSources} with a memo keyed by administrator identity,
 * held for the lifetime of one request only (LRA-270).
 *
 * This reset boundary is load-bearing, not decorative. This application
 * runs FrankenPHP in worker mode (frankenphp/Caddyfile declares a worker
 * block, Dockerfile:96 sets the worker config), so this service instance
 * can survive across many requests inside one long-lived process.
 * Without {@see self::reset()} — called automatically on every
 * `kernel.terminate` because this class implements
 * {@see ResetInterface} and services.yaml's `_defaults.autoconfigure:
 * true` tags any such class `kernel.reset` — a memo written before a
 * revocation would survive it, and "revoking a role takes effect on the
 * next request" would fail in production while passing under the test
 * kernel. It is also what makes LRA-271's expiry checks honest: a
 * support grant that lapses mid-session is gone on the next request
 * because the memo does not survive it. Same pattern as
 * {@see \App\Administration\Infrastructure\Security\SecurityCurrentAdministrator}.
 */
final class RequestScopedEffectivePrivileges implements EffectivePrivileges, ResetInterface
{
    /** @var array<string, PrivilegeGrants> */
    private array $memo = [];

    /**
     * Type-hinted against the {@see EffectivePrivileges} port this class
     * itself implements, not the concrete {@see UnionOfGrantSources} —
     * every other seam in this slice depends on an abstraction, and this
     * one is what would let a future LRA-273 audit/metrics decorator
     * slot in without editing this class. Autowiring cannot resolve this
     * argument on its own, since the port is aliased back to this very
     * class for production; services.yaml binds `$union` explicitly to
     * the union instead.
     */
    public function __construct(
        private readonly EffectivePrivileges $union,
    ) {
    }

    public function forAdministrator(AdministratorId $id): PrivilegeGrants
    {
        return $this->memo[$id->value] ??= $this->union->forAdministrator($id);
    }

    public function reset(): void
    {
        $this->memo = [];
    }
}
