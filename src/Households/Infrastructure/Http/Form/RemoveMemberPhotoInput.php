<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the Profile card's Remove photo button
 * (LRA-207). Carries no fields — the form exists solely to provide
 * CSRF protection for the removal POST, matching the codebase-wide
 * convention that every mutating endpoint goes through a Symfony Form
 * rather than a hand-rolled CSRF check.
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor,
 * matching {@see UpdateMemberProfileInput}'s documented exception to the
 * project's "immutable by default" rule even though this class has no
 * properties to write.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class RemoveMemberPhotoInput
{
}
