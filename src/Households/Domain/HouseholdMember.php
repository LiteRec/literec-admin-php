<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\EmailAddress;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\PhoneNumber;
use App\Households\Domain\ValueObject\ResidencyStatus;
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
}
