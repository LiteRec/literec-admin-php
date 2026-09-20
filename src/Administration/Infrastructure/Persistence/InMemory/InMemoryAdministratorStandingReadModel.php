<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\InMemory;

use App\Administration\Application\Query\Port\AdministratorStandingReadModel;
use App\Administration\Application\Query\View\AdministratorStandingView;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\ValueObject\SignInAccountId;

/**
 * In-memory adapter for the {@see AdministratorStandingReadModel} port.
 * Projects from the same {@see Administrators} port the write side uses
 * (typically {@see InMemoryAdministrators}) rather than keeping a second
 * copy of the data — there is no separate storage to drift out of sync
 * with. Used by domain/application unit tests so they stay #[Small] and
 * never boot Doctrine.
 */
final class InMemoryAdministratorStandingReadModel implements AdministratorStandingReadModel
{
    public function __construct(private readonly Administrators $administrators)
    {
    }

    public function standingFor(SignInAccountId $signInAccountId): ?AdministratorStandingView
    {
        if (!$this->administrators->existsForSignInAccount($signInAccountId)) {
            return null;
        }

        $administrator = $this->administrators->forSignInAccount($signInAccountId);

        return new AdministratorStandingView(
            $administrator->id()->value,
            $administrator->standing()->value,
            $administrator->rankId()->value,
            $administrator->assignedRoles()->toStrings(),
        );
    }
}
