<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Executable form of AC 2 (LRA-267): "No rank grants a privilege
 * directly. Rank exposes no privilege accessor and holds no privilege
 * field; an architecture test fails the build if a dependency from
 * Administration\Domain\Rank to the privilege type is introduced."
 *
 * The subject is {@see \App\Administration\Domain\Rank} plus every type
 * under `Administration\Domain\ValueObject`, since a future change could
 * just as easily smuggle a Privilege dependency into a rank-owned value
 * object (e.g. AssignedRoles) as onto Rank itself.
 *
 * {@see \App\Administration\Domain\ValueObject\PrivilegeSet},
 * {@see \App\Administration\Domain\ValueObject\PrivilegeGrant}, and
 * {@see \App\Administration\Domain\ValueObject\PrivilegeGrants} are
 * excluded: none of the three belongs to Rank — PrivilegeSet is Role's
 * privilege bundle (LRA-266/LRA-268), and PrivilegeGrant/PrivilegeGrants
 * carry a granted privilege's provenance for LRA-270's resolution
 * pipeline — and each one's entire purpose is to hold
 * {@see \App\Administration\Domain\Privilege} cases. Excluding them here
 * is the rule's deliberate carve-out for Role/grant-owned types, not a
 * loophole for Rank.
 */
final class RanksDoNotReferencePrivilegesRule
{
    #[TestRule]
    public function rank_and_its_value_objects_do_not_depend_on_privileges(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(
                Selector::AnyOf(
                    Selector::classname('App\Administration\Domain\Rank'),
                    Selector::inNamespace('App\Administration\Domain\ValueObject'),
                ),
                Selector::Not(Selector::AnyOf(
                    Selector::classname('App\Administration\Domain\ValueObject\PrivilegeSet'),
                    Selector::classname('App\Administration\Domain\ValueObject\PrivilegeGrant'),
                    Selector::classname('App\Administration\Domain\ValueObject\PrivilegeGrants'),
                )),
            ))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::classname('App\Administration\Domain\Privilege'),
                Selector::classname('App\Administration\Domain\PrivilegeDefinition'),
            )
            ->because(
                'AC 2 (LRA-267): a rank never grants a privilege directly — seniority and role '
                . 'assignment are the only things Rank carries.',
            );
    }
}
