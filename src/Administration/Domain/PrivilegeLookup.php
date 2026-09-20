<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\Exception\UnknownPrivilege;

/**
 * Resolves a privilege name against the catalogue.
 *
 * This is the deny-by-default mechanism in its smallest honest form:
 * privilegeNamed() cannot return null and has no "default" parameter, so
 * no implementation and no caller can turn an unrecognised name into a
 * grant. A name that does not match a case throws {@see UnknownPrivilege}.
 */
interface PrivilegeLookup
{
    /**
     * @throws UnknownPrivilege when $name does not match a {@see Privilege} case
     */
    public function privilegeNamed(string $name): Privilege;
}
