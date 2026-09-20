<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Application\Query;

use App\Administration\Application\Query\GetRoleDetail;
use App\Administration\Application\Query\GetRoleDetailHandler;
use App\Administration\Application\Query\ListRoles;
use App\Administration\Application\Query\ListRolesHandler;
use App\Administration\Application\Query\View\RoleDetailView;
use App\Administration\Application\Query\View\RolePrivilegeView;
use App\Administration\Application\Query\View\RoleSummaryView;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Tests\Support\Fake\StubRoleReadModel;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class RoleQueryHandlersTest extends TestCase
{
    private const string ROLE_ID = '019571bf-5d51-7000-b500-00000000ba01';

    #[Test]
    #[TestDox('ListRolesHandler passes includeRetired through to the read model and wraps its result.')]
    public function list_roles_handler_delegates_to_read_model(): void
    {
        $summary = new RoleSummaryView(self::ROLE_ID, 'Front Desk', 2, false);
        $readModel = new StubRoleReadModel(listRolesResult: [$summary]);
        $handler = new ListRolesHandler($readModel);

        $page = $handler(new ListRoles(includeRetired: true));

        self::assertTrue($readModel->includeRetiredReceived);
        self::assertSame([$summary], $page->roles);
    }

    #[Test]
    #[TestDox('GetRoleDetailHandler converts the primitive id and delegates to the read model.')]
    public function get_role_detail_handler_delegates_to_read_model(): void
    {
        $detail = new RoleDetailView(
            self::ROLE_ID,
            'Front Desk',
            'Front-of-house operations.',
            [new RolePrivilegeView('VIEW_USERS', 'View Users')],
            false,
        );
        $readModel = new StubRoleReadModel(roleDetailResult: $detail);
        $handler = new GetRoleDetailHandler($readModel);

        $result = $handler(new GetRoleDetail(self::ROLE_ID));

        self::assertSame(self::ROLE_ID, $readModel->roleIdReceived?->value);
        self::assertSame($detail, $result);
    }

    #[Test]
    #[TestDox('GetRoleDetailHandler propagates RoleNotFound from the read model.')]
    public function get_role_detail_handler_propagates_not_found(): void
    {
        $readModel = new StubRoleReadModel(throwsNotFound: true);
        $handler = new GetRoleDetailHandler($readModel);

        $this->expectException(RoleNotFound::class);
        $handler(new GetRoleDetail(self::ROLE_ID));
    }
}
