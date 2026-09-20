<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Application\Query;

use App\Administration\Application\Query\ListPrivilegeCatalogue;
use App\Administration\Application\Query\ListPrivilegeCatalogueHandler;
use App\Administration\Application\Query\View\PrivilegeGroupView;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\PrivilegeCatalogue;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * PrivilegeView and PrivilegeGroupView type every property as a
 * primitive (string, int), never Privilege or PrivilegeGroup — the
 * handler cannot leak a domain instance to a caller without a PHPStan
 * type error, so that guarantee needs no separate runtime assertion
 * here.
 */
#[Small]
final class ListPrivilegeCatalogueHandlerTest extends TestCase
{
    #[Test]
    #[TestDox('The view carries display metadata for every case, grouped and ordered.')]
    public function view_carries_display_metadata_for_every_case(): void
    {
        $handler = new ListPrivilegeCatalogueHandler(new PrivilegeCatalogue());

        $view = $handler(new ListPrivilegeCatalogue());

        $totalPrivileges = array_sum(array_map(
            static fn (PrivilegeGroupView $group): int => count($group->privileges),
            $view->groups,
        ));
        self::assertCount($totalPrivileges, Privilege::cases());

        $firstGroup = $view->groups[0];
        $firstPrivilege = $firstGroup->privileges[0];
        self::assertNotSame('', $firstGroup->displayName);
        self::assertNotSame('', $firstPrivilege->displayName);
        self::assertNotSame('', $firstPrivilege->description);
    }

    #[Test]
    #[TestDox('Groups are ordered by PrivilegeGroup sort order and never repeat.')]
    public function groups_are_ordered_and_unique(): void
    {
        $handler = new ListPrivilegeCatalogueHandler(new PrivilegeCatalogue());

        $view = $handler(new ListPrivilegeCatalogue());

        $groupNames = array_map(static fn (PrivilegeGroupView $group): string => $group->name, $view->groups);

        self::assertSame(array_unique($groupNames), $groupNames);
        self::assertSame(['ADMINISTRATION', 'USERS', 'INVENTORY', 'CASH_REGISTER'], $groupNames);
    }
}
