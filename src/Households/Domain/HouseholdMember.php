<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;

/**
 * Child entity owned by the {@see Household} aggregate.
 *
 * Although the constructor and mutators are technically `public` (PHP has no
 * package-private modifier), they are considered internal to the aggregate:
 * callers in Application or Infrastructure layers must go through `Household`
 * methods. Direct instantiation outside the aggregate is a programming error.
 */
final class HouseholdMember
{
    private MemberId $id;
    private MemberCode $code;
    private PersonName $name;
    private DateOfBirth $dateOfBirth;
    private Gender $gender;
    private ?EmailAddress $email;
    private ?PhoneNumber $phone;
    private ResidencyStatus $residencyStatus;
    private bool $isPrimary;
    private bool $isActive;
    private ?string $deactivatedReason;
    private ?DateTimeImmutable $deactivatedAt;
    /**
     * Back-reference to the owning {@see Household}. Required by the
     * Doctrine persistence mapping (many-to-one inverse) so that adding a
     * member to a household persists the FK without a second flush. Set
     * once by {@see Household::register()} / {@see Household::addMember()}
     * via {@see self::attachToHousehold()} and never reassigned. The
     * property is left uninitialized rather than nullable because the
     * Doctrine many-to-one mapping is declared non-nullable; a
     * HouseholdMember without an owning household is a programming error
     * and surfaces immediately as a property-access error rather than as
     * a silent NULL.
     */
    private Household $household;

    /**
     * Internal-to-aggregate constructor. Use {@see Household::register()} or
     * {@see Household::addMember()} to create instances.
     */
    public function __construct(
        MemberId $id,
        MemberCode $code,
        PersonName $name,
        DateOfBirth $dateOfBirth,
        Gender $gender,
        ?EmailAddress $email,
        ?PhoneNumber $phone,
        ResidencyStatus $residencyStatus,
        bool $isPrimary,
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->dateOfBirth = $dateOfBirth;
        $this->gender = $gender;
        $this->email = $email;
        $this->phone = $phone;
        $this->residencyStatus = $residencyStatus;
        $this->isPrimary = $isPrimary;
        $this->isActive = true;
        $this->deactivatedReason = null;
        $this->deactivatedAt = null;
    }

    public function id(): MemberId
    {
        return $this->id;
    }

    public function code(): MemberCode
    {
        return $this->code;
    }

    public function name(): PersonName
    {
        return $this->name;
    }

    public function dateOfBirth(): DateOfBirth
    {
        return $this->dateOfBirth;
    }

    public function gender(): Gender
    {
        return $this->gender;
    }

    public function email(): ?EmailAddress
    {
        return $this->email;
    }

    public function phone(): ?PhoneNumber
    {
        return $this->phone;
    }

    public function residencyStatus(): ResidencyStatus
    {
        return $this->residencyStatus;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function deactivatedReason(): ?string
    {
        return $this->deactivatedReason;
    }

    public function deactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function rename(PersonName $name): void
    {
        $this->name = $name;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function updateDateOfBirth(DateOfBirth $dateOfBirth): void
    {
        $this->dateOfBirth = $dateOfBirth;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function updateGender(Gender $gender): void
    {
        $this->gender = $gender;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function updateContact(?EmailAddress $email, ?PhoneNumber $phone): void
    {
        $this->email = $email;
        $this->phone = $phone;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function changeResidency(ResidencyStatus $status): void
    {
        $this->residencyStatus = $status;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function deactivate(string $reason, DateTimeImmutable $at): void
    {
        $this->isActive = false;
        $this->deactivatedReason = $reason;
        $this->deactivatedAt = $at;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function reactivate(): void
    {
        $this->isActive = true;
        $this->deactivatedReason = null;
        $this->deactivatedAt = null;
    }

    /**
     * @internal Called by {@see Household::register()} and
     *           {@see Household::addMember()} to link a freshly-constructed
     *           member to its owning aggregate so the Doctrine many-to-one
     *           FK persists on flush. Idempotent: re-attaching to the same
     *           household is a no-op; attaching to a different one is a
     *           programming error.
     */
    public function attachToHousehold(Household $household): void
    {
        if (isset($this->household)) {
            if ($this->household === $household) {
                return;
            }
            throw new \LogicException(
                'HouseholdMember is already attached to a different Household.',
            );
        }
        $this->household = $household;
    }
}
