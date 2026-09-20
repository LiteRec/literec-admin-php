<?php

declare(strict_types=1);

namespace App\Tests\Functional\Administration;

use App\Administration\Domain\Privilege;
use App\Tests\Support\Trait\GrantsPrivileges;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * LRA-270 acceptance criterion 1: a request carrying a forged or
 * modified permission set is rejected. Both cases below call the same
 * real `#[IsGranted]`-gated endpoint ({@see \App\Administration\Infrastructure\Http\Controller\RoleListController})
 * directly, forging the privilege into the request body, a header, and a
 * query parameter simultaneously. {@see \App\Administration\Infrastructure\Security\PrivilegeVoter}
 * never reads the Request at all — it resolves the administrator
 * identity from the authenticated token and the privilege set from
 * {@see \App\Administration\Domain\EffectivePrivileges} — so none of the
 * three forgeries can influence the outcome.
 *
 * The no-administrator case alone is invariant to the forgery: it denies
 * at {@see \App\Administration\Infrastructure\Security\PrivilegeVoter::resolveGrant()}'s
 * `$standing === null` branch before {@see \App\Administration\Domain\EffectivePrivileges}
 * is ever consulted, so it would pass identically with the three forged
 * inputs deleted. The second case is the one that actually exercises
 * "the forged privilege was ignored": an administrator who genuinely
 * holds a different, real privilege still gets refused the one they
 * forged, proving the voter consulted the resolved set rather than the
 * request.
 */
#[Large]
#[Group('database')]
final class ForgedPermissionSetIsIgnoredTest extends WebTestCase
{
    use GrantsPrivileges;

    private const string USERNAME = 'forged_permissions_e2e';

    private const string OTHER_USERNAME = 'forged_permissions_other_e2e';

    #[Test]
    #[TestDox('A privileged endpoint denies a non-administrator despite a forged body, header, and query parameter.')]
    public function forged_permission_set_is_ignored_for_a_non_administrator(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::USERNAME, self::TEST_PASSWORD);

        $this->requestRolesListForging($client, Privilege::ManageAdminRanks);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    #[TestDox('An administrator holding one privilege cannot forge a different one into the request.')]
    public function forged_privilege_is_ignored_for_a_real_administrator(): void
    {
        $client = static::createClient();
        // Grants ViewUsers only — never ManageAdminRanks, the privilege
        // the request below forges.
        $this->signInAdministratorWithPrivilege($client, self::OTHER_USERNAME, Privilege::ViewUsers);

        $this->requestRolesListForging($client, Privilege::ManageAdminRanks);

        self::assertResponseStatusCodeSame(403);
    }

    private function requestRolesListForging(KernelBrowser $client, Privilege $forgedPrivilege): void
    {
        $client->request(
            'GET',
            '/admin/administration/roles?rights[]=' . $forgedPrivilege->value,
            server: [
                'HTTP_X_PERMISSIONS' => $forgedPrivilege->value,
            ],
            content: json_encode(['rights' => [$forgedPrivilege->value]], JSON_THROW_ON_ERROR),
        );
    }
}
