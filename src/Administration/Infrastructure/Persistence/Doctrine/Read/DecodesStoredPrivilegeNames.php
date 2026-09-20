<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Read;

use App\Administration\Domain\Exception\UnknownPrivilege;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\PrivilegeLookup;

/**
 * Shared JSONB-privileges-column decoding for {@see RankRoleGrants} and
 * {@see DirectRoleGrants}: both project a role's `privileges` column
 * (see administration_roles) into catalogue {@see Privilege} cases via
 * the injected {@see PrivilegeLookup}, rather than {@see Privilege::tryFrom()}
 * directly, so an unrecognised stored name is dropped AND logged through
 * the one seam {@see \App\Administration\Infrastructure\Authorization\CataloguePrivilegeLookup}
 * owns — this is where LRA-266's "unknown privilege denies and is
 * logged" acceptance criterion is actually satisfied, once, for every
 * source, rather than each source re-implementing its own drop-and-log.
 *
 * Extracted up front rather than duplicated across the two sources:
 * SonarCloud's new-code duplication threshold is 3%, and this decoding
 * shape is otherwise near-identical in both files.
 */
trait DecodesStoredPrivilegeNames
{
    /**
     * @param array<string, mixed> $row
     * @return list<Privilege>
     */
    private function decodeGrantedPrivileges(array $row, string $column, PrivilegeLookup $privilegeLookup): array
    {
        $raw = $row[$column] ?? null;
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (!is_array($decoded)) {
            return [];
        }

        $privileges = [];
        foreach (array_filter($decoded, is_string(...)) as $name) {
            try {
                $privileges[] = $privilegeLookup->privilegeNamed($name);
            } catch (UnknownPrivilege) {
                // Dropped and logged by PrivilegeLookup itself; nothing
                // further to do at this call site.
                continue;
            }
        }

        return $privileges;
    }
}
