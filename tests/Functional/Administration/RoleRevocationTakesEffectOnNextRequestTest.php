<?php

declare(strict_types=1);

namespace App\Tests\Functional\Administration;

use App\Administration\Application\Command\RevokePrivilegeFromRole;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Tests\Support\Trait\GrantsPrivileges;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * LRA-270 acceptance criterion 2: revoking a role's privilege takes
 * effect starting with the very next request, not retroactively within
 * the request still in flight and not "eventually" on some later
 * request either.
 *
 * `$client->disableReboot()` keeps the same kernel — and therefore the
 * same {@see \App\Administration\Infrastructure\Security\RequestScopedEffectivePrivileges}
 * service instance — alive across both `$client->request()` calls below,
 * simulating FrankenPHP worker mode, where a service instance can
 * survive many requests inside one long-lived process. A normal
 * test-client reboot between requests would trivially pass even with a
 * memoisation bug this test exists to catch, since a fresh instance
 * would replace the memoising one on every call regardless of whether
 * `reset()` is wired correctly — same rationale as LRA-279's
 * RevocationTakesEffectNextRequestTest.
 */
#[Large]
#[Group('database')]
final class RoleRevocationTakesEffectOnNextRequestTest extends WebTestCase
{
    use GrantsPrivileges;

    private const string USERNAME = 'role_revocation_e2e';

    #[Test]
    #[TestDox('Revoking a role privilege takes effect on the next request; the operator stays signed in.')]
    public function role_privilege_revocation_takes_effect_on_the_next_request(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $granted = $this->signInAdministratorWithPrivilege($client, self::USERNAME, Privilege::ManageAdminRanks);
        $roleId = $granted['roleId'];

        $client->request('GET', '/admin/administration/roles');
        self::assertResponseIsSuccessful();

        $bus = static::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new RevokePrivilegeFromRole(
            $roleId->value,
            Privilege::ManageAdminRanks->value,
            ActorKind::System->value,
        ));

        $client->request('GET', '/admin/administration/roles');
        self::assertResponseStatusCodeSame(403);

        // Still signed in — the session itself was never invalidated,
        // only the privilege check for this specific endpoint.
        $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
    }
}
