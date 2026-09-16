<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\Exception\InvariantViolation;
use App\Households\Domain\ValueObject\AnonymizedProfile;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Deactivation;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\Height;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdLink;
use App\Households\Domain\ValueObject\ImageFormat;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberContact;
use App\Households\Domain\ValueObject\MemberHouseholdLinks;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberLifecycle;
use App\Households\Domain\ValueObject\MemberMerge;
use App\Households\Domain\ValueObject\MemberProfile;
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
 *
 * The scalar/embeddable properties below stay mapped individually in
 * {@see \App\Households\Infrastructure\Persistence\Doctrine\Mapping\HouseholdMember.orm.xml}
 * (LRA-237): {@see self::profile()}, {@see self::contact()},
 * {@see self::lifecycle()}, and {@see self::householdLinks()} are read
 * projections built from them on demand, not additional storage, so
 * callers read one cohesive value object instead of several loose getters.
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
     * once in the constructor and never reassigned — the property is left
     * uninitialized rather than nullable because the Doctrine many-to-one
     * mapping is declared non-nullable; a HouseholdMember without an
     * owning household is a programming error and surfaces immediately as
     * a property-access error rather than as a silent NULL.
     */
    private Household $household;

    /**
     * Internal-to-aggregate constructor. Use {@see Household::register()} or
     * {@see Household::addMember()} to create instances.
     */
    public function __construct(
        MemberId $id,
        MemberCode $code,
        MemberProfile $profile,
        MemberContact $contact,
        ResidencyStatus $residencyStatus,
        bool $isPrimary,
        Household $household,
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $profile->name;
        $this->dateOfBirth = $profile->dateOfBirth;
        $this->gender = $profile->gender;
        $this->salutation = $profile->salutation;
        $this->height = $profile->height;
        $this->weight = $profile->weight;
        $this->email = $contact->email;
        $this->phone = $contact->phone;
        $this->residencyStatus = $residencyStatus;
        $this->isPrimary = $isPrimary;
        $this->isActive = true;
        $this->deactivatedReason = null;
        $this->deactivatedAt = null;
        $this->anonymizedAt = null;
        $this->mergedIntoMemberId = null;
        $this->mergedAt = null;
        $this->photoStorageKey = null;
        $this->photoFormat = null;
        $this->photoUploadedAt = null;
        $this->affiliations = new ArrayCollection();
        $this->household = $household;
    }

    public function id(): MemberId
    {
        return $this->id;
    }

    public function code(): MemberCode
    {
        return $this->code;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function residencyStatus(): ResidencyStatus
    {
        return $this->residencyStatus;
    }

    public function profile(): MemberProfile
    {
        return MemberProfile::of(
            $this->name,
            $this->dateOfBirth,
            $this->gender,
            $this->salutation,
            $this->height,
            $this->weight,
        );
    }

    public function contact(): MemberContact
    {
        return MemberContact::of($this->email, $this->phone);
    }

    /**
     * The member's lifecycle state, materialized from the four persisted
     * scalar facts so they are never exposed as six loose getters.
     */
    /**
     * @throws InvariantViolation when $mergedIntoMemberId is set without
     *                            $mergedAt — markMergedInto() always writes
     *                            both together, so this is a persistence
     *                            bug, never a legitimately unmerged member.
     */
    public function lifecycle(): MemberLifecycle
    {
        $deactivation = $this->deactivatedReason !== null && $this->deactivatedAt !== null
            ? new Deactivation($this->deactivatedReason, $this->deactivatedAt)
            : null;

        if ($this->mergedIntoMemberId !== null && $this->mergedAt === null) {
            throw InvariantViolation::with('merged_into_member_id is set without merged_at.');
        }

        // mergedIntoMemberId is the merge fact — the single source of truth
        // isMerged() must agree with (DoctrineHouseholds::lockUnmergedMember()
        // and the read models key on it alone); mergedAt is guaranteed
        // non-null here by the guard above.
        $merge = $this->mergedIntoMemberId !== null
            ? new MemberMerge($this->mergedIntoMemberId, $this->mergedAt)
            : null;

        return new MemberLifecycle($this->isActive, $deactivation, $this->anonymizedAt, $merge);
    }

    /**
     * The member's uploaded profile photo, or null when none has been
     * uploaded. Materialized from the persisted scalar fields — this is a
     * read of already-validated state, not re-validation.
     */
    public function photo(): ?ProfilePhoto
    {
        if ($this->photoStorageKey === null || $this->photoFormat === null || $this->photoUploadedAt === null) {
            return null;
        }

        return new ProfilePhoto($this->photoStorageKey, $this->photoFormat, $this->photoUploadedAt);
    }

    /**
     * Every household this member has been shared with (LRA-210),
     * materialized from the {@see self::$affiliations} collection.
     */
    public function householdLinks(): MemberHouseholdLinks
    {
        return MemberHouseholdLinks::of(...array_map(
            static fn(HouseholdAffiliation $a): HouseholdLink => HouseholdLink::of($a->householdId(), $a->linkedAt()),
            $this->affiliations->toArray(),
        ));
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function updateProfile(MemberProfile $profile): void
    {
        $this->name = $profile->name;
        $this->dateOfBirth = $profile->dateOfBirth;
        $this->gender = $profile->gender;
        $this->salutation = $profile->salutation;
        $this->height = $profile->height;
        $this->weight = $profile->weight;
    }

    /**
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function updateContact(MemberContact $contact): void
    {
        $this->email = $contact->email;
        $this->phone = $contact->phone;
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
     * operator-typed text — as the deactivation reason. Also clears
     * salutation, height, weight, and any attached profile photo (the
     * photo's storage key must still be read by the caller beforehand —
     * {@see self::photo()} — to release the file via
     * {@see \App\Households\Domain\Event\MemberPhotoReleased}, the same
     * two-step split {@see MemberInHousehold::removePhoto()} uses). Irreversible:
     * there is no `unanonymize()`; {@see Household} refuses every further
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
        $this->salutation = $profile->salutation;
        $this->height = $profile->height;
        $this->weight = $profile->weight;
        $this->replacePhoto(null);
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
     * Replaces the member's profile photo; a null $photo clears it.
     * Absorbs what were previously separate attachPhoto()/removePhoto()
     * mutators (LRA-237) — the intention-revealing attach/remove verbs
     * stay on the {@see Household} aggregate API where callers see them,
     * this entity-level primitive is @internal to the aggregate exactly as
     * the mutators it replaces were.
     *
     * @internal Mutation must be triggered via {@see Household} aggregate.
     */
    public function replacePhoto(?ProfilePhoto $photo): void
    {
        $this->photoStorageKey = $photo?->storageKey;
        $this->photoFormat = $photo?->format;
        $this->photoUploadedAt = $photo?->uploadedAt;
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
