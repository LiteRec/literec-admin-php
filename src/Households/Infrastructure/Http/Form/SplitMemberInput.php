<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the split-member dialog (LRA-209).
 *
 * Mutable on purpose: Symfony Forms write back through PropertyAccessor and
 * the Application command DTO
 * {@see \App\Households\Application\Command\SplitMember} is `final readonly`.
 * Created and consumed entirely inside the Households HTTP adapter.
 *
 * @internal Belongs to the Households HTTP boundary; never referenced from
 *           Domain or Application code.
 */
final class SplitMemberInput
{
    public ?string $firstName = null;

    public ?string $lastName = null;

    public ?string $middleName = null;

    public ?string $suffix = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $reason = null;

    /** @var list<string> */
    public array $transactionIds = [];
}
