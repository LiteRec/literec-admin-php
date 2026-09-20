<?php

declare(strict_types=1);

namespace App\Tests\Functional\Administration;

use App\Administration\Domain\Privilege;
use App\Tests\Support\Trait\SignsInUsers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * LRA-270 acceptance criterion 1: a request carrying a forged or
 * modified permission set is rejected. Signs in an ordinary user with no
 * administrator record at all, then calls a real `#[IsGranted]`-gated
 * endpoint ({@see \App\Administration\Infrastructure\Http\Controller\RoleListController})
 * directly, forging the privilege into the request body, a header, and a
 * query parameter simultaneously. {@see \App\Administration\Infrastructure\Security\PrivilegeVoter}
 * never reads the Request at all — it resolves the administrator
 * identity from the authenticated token and the privilege set from
 * {@see \App\Administration\Domain\EffectivePrivileges} — so none of the
 * three forgeries can influence the outcome.
 */
#[Large]
#[Group('database')]
final class ForgedPermissionSetIsIgnoredTest extends WebTestCase
{
    use SignsInUsers;

    private const string USERNAME = 'forged_permissions_e2e';

    #[Test]
    #[TestDox('A privileged endpoint denies a non-administrator despite a forged body, header, and query parameter.')]
    public function forged_permission_set_is_ignored(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::USERNAME, self::TEST_PASSWORD);

        $client->request(
            'GET',
            '/admin/administration/roles?rights[]=' . Privilege::ManageAdminRanks->value,
            server: [
                'HTTP_X_PERMISSIONS' => Privilege::ManageAdminRanks->value,
            ],
            content: json_encode(['rights' => [Privilege::ManageAdminRanks->value]], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
    }
}
