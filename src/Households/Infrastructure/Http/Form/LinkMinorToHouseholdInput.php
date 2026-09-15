<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the Household card's "Link Shared
 * Member" action (LRA-210): the id of the minor selected via the member
 * lookup dialog.
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor,
 * matching {@see UpdateMemberProfileInput}'s documented exception to the
 * project's "immutable by default" rule.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class LinkMinorToHouseholdInput
{
    public ?string $memberId = null;
}
