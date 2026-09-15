<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the linked-households list's "Unlink"
 * button (LRA-210). Carries no fields — the household and member are both
 * already in the route path; the form exists solely to provide CSRF
 * protection, matching the codebase-wide convention that every mutating
 * endpoint goes through a Symfony Form rather than a hand-rolled CSRF
 * check (see {@see RemoveMemberPhotoInput} for the same fieldless shape).
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class WithdrawMinorFromHouseholdInput
{
}
