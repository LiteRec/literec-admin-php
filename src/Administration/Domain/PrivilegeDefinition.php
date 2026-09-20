<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\Exception\InvalidPrivilegeDefinition;

/**
 * The screen metadata a {@see Privilege} case carries: what to show an
 * operator building a role, which folder it is filed under, where it
 * falls in that folder's display order, and how closely its use must be
 * watched.
 */
final readonly class PrivilegeDefinition
{
    public function __construct(
        public string $displayName,
        public string $description,
        public PrivilegeGroup $group,
        public int $order,
        public PrivilegeRisk $risk = PrivilegeRisk::Standard,
    ) {
        if (trim($displayName) === '') {
            throw InvalidPrivilegeDefinition::emptyDisplayName();
        }

        if (trim($description) === '') {
            throw InvalidPrivilegeDefinition::emptyDescription();
        }

        if ($order < 0) {
            throw InvalidPrivilegeDefinition::negativeOrder($order);
        }
    }
}
