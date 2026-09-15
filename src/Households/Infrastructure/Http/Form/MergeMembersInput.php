<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the merge-confirmation dialog (LRA-208).
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor and
 * the Application command DTO
 * {@see \App\Households\Application\Command\MergeMembers} is `final readonly`.
 * Created and consumed entirely inside the Households HTTP adapter.
 *
 * `acknowledged` backs the destructive-action confirmation checkbox — the
 * merge is irreversible from the UI, so submission is refused unless it is
 * checked. `duplicateHouseholdId` is carried only so the controller can
 * re-render the side-by-side confirm dialog on a validation failure; the
 * domain command ({@see \App\Households\Application\Command\MergeMembers})
 * does not need it — {@see \App\Households\Application\Command\MergeMembersHandler}
 * resolves the duplicate's household via `Households::findByMemberId()`.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class MergeMembersInput
{
    public ?string $duplicateMemberId = null;

    public ?string $duplicateHouseholdId = null;

    public bool $acknowledged = false;
}
