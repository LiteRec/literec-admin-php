<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Read;

use App\Administration\Domain\PrivilegeLookup;
use App\Administration\Domain\ValueObject\GrantOrigin;
use App\Administration\Domain\ValueObject\PrivilegeGrant;
use App\Administration\Domain\ValueObject\PrivilegeGrants;
use App\Shared\Infrastructure\Doctrine\Read\RowFieldExtraction;

/**
 * Shared row-to-grants projection for {@see RankRoleGrants} and
 * {@see DirectRoleGrants}: both fetch a `role_id`/`role_name`/`privileges`
 * row shape from a different join path and only differ in which
 * {@see GrantOrigin} the resulting grants carry — this is where that
 * shape becomes {@see PrivilegeGrant} instances.
 *
 * Extracted up front rather than duplicated across the two sources:
 * SonarCloud's new-code duplication threshold is 3%, and the two
 * sources' `grantsFor()` bodies were otherwise near-identical.
 */
trait MapsRoleRowsToPrivilegeGrants
{
    use RowFieldExtraction;
    use DecodesStoredPrivilegeNames;

    /**
     * @param list<array<string, mixed>> $rows each carrying role_id,
     *        role_name, and privileges columns
     */
    private function grantsFromRoleRows(
        array $rows,
        GrantOrigin $origin,
        PrivilegeLookup $privilegeLookup,
    ): PrivilegeGrants {
        $grants = [];
        foreach ($rows as $row) {
            $roleId = $this->rowString($row, 'role_id');
            $roleName = $this->rowString($row, 'role_name');

            foreach ($this->decodeGrantedPrivileges($row, 'privileges', $privilegeLookup) as $privilege) {
                $grants[] = new PrivilegeGrant($privilege, $origin, $roleId, $roleName);
            }
        }

        return PrivilegeGrants::of(...$grants);
    }
}
