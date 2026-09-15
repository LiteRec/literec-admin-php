<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\ValueObject\AnonymizedProfile;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Deactivation;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\Height;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\ImageFormat;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberMerge;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ProfilePhoto;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Domain\ValueObject\Salutation;
use App\Households\Domain\ValueObject\Weight;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

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
    /**
     * Fixed reason recorded on {@see self::$deactivatedReason} when
     * anonymization also deactivates the member (LRA-212) — never
     * operator-typed text, since {@see self::deactivate()}'s reason
     * parameter is exactly the kind of free text anonymization exists to
     * scrub.
     */
    private const string ANONYMIZED_DEACTIVATION_REASON = 'Anonymized';

    private MemberId $id;
    private MemberCode $code;
    private PersonName $name;
    private DateOfBirth $dateOfBirth;
    private Gender $gender;
    private ?EmailAddress $email;
    private ?PhoneNumber $phone;
    private ?Salutation $salutation;
    private ?Height $height;
    private ?Weight $weight;
    private ResidencyStatus $residencyStatus;
    private bool $isPrimary;
    private bool $isActive;
    private ?string $deactivatedReason;
    private ?DateTimeImmutable $deactivatedAt;
    private ?DateTimeImmutable $anonymizedAt;
    private ?MemberId $mergedIntoMemberId;
    private ?DateTimeImmutable $mergedAt;
    private ?string $photoStorageKey;
    private ?ImageFormat $photoFormat;
    private ?DateTimeImmutable $photoUploadedAt;
    /**
     * Households this (necessarily minor) member has been shared with
     * (LRA-210), in addition to this — their home — household. Held as a
     * Doctrine-compatible {@see Collection} for the same reason
     * {@see Household::$members} is: the persistence adapter maps this via
     * a one-to-many association.
     *
     * @var Collection<int, HouseholdAffiliation>
     */
    private Collection $affiliations;
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
        ?Salutation $salutation = null,
        ?Height $height = null,
        ?Weight $weight = null,
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
        $this->anonymizedAt = null;
        $this->mergedIntoMemberId = null;
        $this->mergedAt = null;
        $this->salutation = $salutation;
        $this->height = $height;
        $this->weight = $weight;
        $this->photoStorageKey = null;
        $this->photoFormat = null;
        $this->photoUploadedAt = null;
        $this->affiliations = new ArrayCollection();
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

    public function salutation(): ?Salutation
    {
        return $this->salutation;
    }

    public function height(): ?Height
    {
        return $this->height;
    }

    public function weight(): ?Weight
    {
        return $this->weight;
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

    public function isAnonymized(): bool
    {
        return $this->anonymizedAt !== null;
    }

    public function anonymizedAt(): ?DateTimeImmutable
    {
        return $this->anonymizedAt;
    }

    /**
     * The deactivation record (reason + timestamp), or null while the member
     * is active. Materialized from the persisted scalar fields so the pair is
     * never exposed as two loose nullable getters.
     */
    public function deactivation(): ?Deactivation
    {
        if ($this->deactivatedReason === null || $this->deactivatedAt === null) {
            return null;
        }

        return new Deactivation($this->deactivatedReason, $this->deactivatedAt);
    }

    public function isMerged(): bool
    {
        return $this->mergedIntoMemberId !== null;
    }

    /**
     * The merge record (survivor id + timestamp), or null while the member
     * has not been merged. Materialized from the persisted scalar fields the
     * same way {@see self::deactivation()} projects {@see Deactivation}.
     */
    public function merge(): ?MemberMerge
    {
        if ($this->mergedIntoMemberId === null || $this->mergedAt === null) {
            return null;
        }

        return new MemberMerge($this->mergedIntoMemberId, $this->mergedAt);
    }

    /**
     * The member's uploaded profile photo, or null when none has been
     * uploaded. Materialized from the persisted scalar fields the same
     * way {@see self::deactivation()} projects {@see Deactivation} — this
     * is a read of already-validated state, not re-validation.
     */
    public function photo(): ?ProfilePhoto
    {
        if ($this->photoStorageKey === null || $this->photoFormat === null || $this->photoUploadedAt === null) {
            return null;
        }

        return new ProfilePhoto($this->photoStorageKey, $this->photoFormat, $this->photoUploadedAt);
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
    public function updateSalutation(?Salutation $salutation): void
    {
        $this->salutation = $salutation;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function updateMeasurements(?Height $height, ?Weight $weight): void
    {
        $this->height = $height;
        $this->weight = $weight;
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
     * Replaces every scrubbed identity field with {@see AnonymizedProfile}'s
     * placeholder values and deactivates the member in the same call, with
     * the fixed {@see self::ANONYMIZED_DEACTIVATION_REASON} — never
     * operator-typed text — as the deactivation reason. Irreversible: there
     * is no `unanonymize()`; {@see Household} refuses every further
     * mutator against this member once {@see self::$anonymizedAt} is set.
     *
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function anonymize(AnonymizedProfile $profile, DateTimeImmutable $at): void
    {
        $this->name = $profile->name;
        $this->dateOfBirth = $profile->dateOfBirth;
        $this->gender = $profile->gender;
        $this->email = null;
        $this->phone = null;
        $this->isActive = false;
        $this->deactivatedReason = self::ANONYMIZED_DEACTIVATION_REASON;
        $this->deactivatedAt = $at;
        $this->anonymizedAt = $at;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function markMergedInto(MemberId $survivorId, DateTimeImmutable $at): void
    {
        $this->mergedIntoMemberId = $survivorId;
        $this->mergedAt = $at;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function attachPhoto(ProfilePhoto $photo): void
    {
        $this->photoStorageKey = $photo->storageKey;
        $this->photoFormat = $photo->format;
        $this->photoUploadedAt = $photo->uploadedAt;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function removePhoto(): void
    {
        $this->photoStorageKey = null;
        $this->photoFormat = null;
        $this->photoUploadedAt = null;
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

    /**
     * @return list<HouseholdId>
     */
    public function sharedHouseholdIds(): array
    {
        return array_values(array_map(
            static fn(HouseholdAffiliation $a): HouseholdId => $a->householdId(),
            $this->affiliations->toArray(),
        ));
    }

    public function isSharedWith(HouseholdId $householdId): bool
    {
        foreach ($this->affiliations as $affiliation) {
            if ($affiliation->householdId()->equals($householdId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The timestamp the member was shared with $householdId, or null when
     * not currently shared with it. Materialized from the affiliations
     * collection the same way {@see self::deactivation()} projects
     * {@see Deactivation} — a read of already-validated state.
     */
    public function linkedAtFor(HouseholdId $householdId): ?DateTimeImmutable
    {
        foreach ($this->affiliations as $affiliation) {
            if ($affiliation->householdId()->equals($householdId)) {
                return $affiliation->linkedAt();
            }
        }

        return null;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function shareWith(HouseholdId $householdId, DateTimeImmutable $at): void
    {
        $this->affiliations->add(new HouseholdAffiliation($this, $householdId, $at));
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     *           No-op when the member is not currently shared with
     *           $householdId.
     */
    public function withdrawFrom(HouseholdId $householdId): void
    {
        foreach ($this->affiliations as $affiliation) {
            if ($affiliation->householdId()->equals($householdId)) {
                $this->affiliations->removeElement($affiliation);

                return;
            }
        }
    }
}
