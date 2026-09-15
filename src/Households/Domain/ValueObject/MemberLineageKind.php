<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

/**
 * Discriminates the rows of the shared `household_member_lineage` audit
 * table (LRA-208). Backed by a string, not a Postgres enum, matching
 * {@see ResidencyStatus}'s convention for this schema.
 *
 * LRA-208 (merge) writes only {@see self::MergedInto} rows. LRA-209
 * (split) adds a writer for {@see self::SplitFrom}; the table and this
 * enum are shared between the two tickets so both audit trails live in
 * one place.
 */
enum MemberLineageKind: string
{
    case MergedInto = 'MERGED_INTO';
    case SplitFrom = 'SPLIT_FROM';
}
