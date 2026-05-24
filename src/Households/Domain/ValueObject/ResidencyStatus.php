<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

/**
 * The household-member residency category used for pricing rules, program
 * eligibility, and reporting cuts. Cases are intentionally a flat enum
 * (not split into separate location-vs-role types) because the legacy
 * system models them as one column and downstream rules branch on a
 * single value; splitting risks losing the parity guarantee on read.
 *
 *   - Resident:    Lives within the agency's service-area boundary.
 *   - NonResident: Lives outside the service-area boundary.
 *   - Member:      Holds an active membership and gets member pricing
 *                  regardless of physical residency.
 *   - Staff:       Employee of the agency; receives staff pricing/access.
 *
 * Exactly one status applies to a member at a time. Transitions are
 * append-only via household_residency_history (LRA-37 migration); there
 * are no forbidden transitions at the domain level — operations staff
 * may move a member between any two statuses with an effective-from date.
 */
enum ResidencyStatus: string
{
    case Resident = 'RESIDENT';
    case NonResident = 'NON_RESIDENT';
    case Member = 'MEMBER';
    case Staff = 'STAFF';
}
