<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\Event\HouseholdRegistered;
use App\Households\Domain\Event\MemberAddedToHousehold;
use App\Households\Domain\Event\HouseholdAddressUpdated;
use App\Households\Domain\Event\MemberContactUpdated;
use App\Households\Domain\Event\MemberDeactivated;
use App\Households\Domain\Event\MemberMergedInto;
use App\Households\Domain\Event\MemberPhotoAttached;
use App\Households\Domain\Event\MemberPhotoReleased;
use App\Households\Domain\Event\MemberPhotoRemoved;
use App\Households\Domain\Event\MemberProfileUpdated;
use App\Households\Domain\Event\MemberReactivated;
use App\Households\Domain\Event\MemberRemovedFromHousehold;
use App\Households\Domain\Event\MemberResidencyChanged;
use App\Households\Domain\Exception\CannotMergeMemberIntoItself;
use App\Households\Domain\Exception\DuplicateMemberCode;
use App\Households\Domain\Exception\DuplicateMemberId;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\Height;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
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
use Psr\Clock\ClockInterface;

/**
 * Household aggregate root.
 *
 * Owns a list of {@see HouseholdMember} child entities and a single
 * household-level {@see Address}. Every state change records a domain event;
 * a Messenger middleware dispatches those post-transaction.
 *
 * On the email/phone parameters: the aggregate accepts them as nullable on
 * the member-level mutators ({@see self::register()}, {@see self::addMember()},
 * {@see self::updateMemberContact()}) so contact-only edits — including
 * "remove email", "remove phone" — can be expressed without re-asking for
 * other profile fields. The command DTOs in the Application layer
 * ({@see \App\Households\Application\Command\RegisterHousehold},
 * {@see \App\Households\Application\Command\AddMemberToHousehold}) currently
 * require both at registration/add time because the legacy "view users" UI
 * never created a member without contact info; LRA-43 may relax that.
 */
final class Household
{
    use AggregateRoot;

    private HouseholdId $id;
    private HouseholdName $name;
    private Address $address;
    /**
     * Held as a Doctrine-compatible {@see Collection} so the persistence
     * adapter can map this aggregate via a one-to-many association. The
     * public {@see self::members()} accessor still returns a plain list to
     * keep the domain API framework-agnostic for callers.
     *
     * @var Collection<int, HouseholdMember>
     */
    private Collection $members;
    private DateTimeImmutable $createdAt;

    private function __construct()
    {
        $this->members = new ArrayCollection();
    }

    public static function register(
        HouseholdId $id,
        HouseholdName $name,
        Address $address,
        MemberId $primaryMemberId,
        MemberCode $primaryMemberCode,
        PersonName $primaryMemberName,
        DateOfBirth $primaryMemberDob,
        Gender $primaryMemberGender,
        ?EmailAddress $primaryMemberEmail,
        ?PhoneNumber $primaryMemberPhone,
        ResidencyStatus $primaryMemberResidency,
        ClockInterface $clock,
        ?Salutation $primaryMemberSalutation = null,
        ?Height $primaryMemberHeight = null,
        ?Weight $primaryMemberWeight = null,
    ): self {
        $household = new self();
        $household->id = $id;
        $household->name = $name;
        $household->address = $address;
        $household->createdAt = $clock->now();

        $household->recordThat(new HouseholdRegistered($id, $name, $household->createdAt));

        $primary = new HouseholdMember(
            $primaryMemberId,
            $primaryMemberCode,
            $primaryMemberName,
            $primaryMemberDob,
            $primaryMemberGender,
            $primaryMemberEmail,
            $primaryMemberPhone,
            $primaryMemberResidency,
            true,
            $primaryMemberSalutation,
            $primaryMemberHeight,
            $primaryMemberWeight,
        );
        $primary->attachToHousehold($household);
        $household->members->add($primary);
        $household->recordThat(new MemberAddedToHousehold(
            $id,
            $primaryMemberId,
            $primaryMemberCode,
            $primaryMemberName,
            true,
            $household->createdAt,
        ));

        return $household;
    }

    public function id(): HouseholdId
    {
        return $this->id;
    }

    public function name(): HouseholdName
    {
        return $this->name;
    }

    public function address(): Address
    {
        return $this->address;
    }

    /**
     * Returns a defensive copy: each {@see HouseholdMember} is cloned so
     * callers cannot mutate the aggregate's internal members through their
     * (public-for-aggregate-use-only) state-change methods. All real
     * mutations must flow through Household's intention-revealing methods.
     *
     * @return list<HouseholdMember>
     */
    public function members(): array
    {
        return array_values(array_map(
            static fn(HouseholdMember $m): HouseholdMember => clone $m,
            $this->members->toArray(),
        ));
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function addMember(
        MemberId $memberId,
        MemberCode $memberCode,
        PersonName $name,
        DateOfBirth $dateOfBirth,
        Gender $gender,
        ?EmailAddress $email,
        ?PhoneNumber $phone,
        ResidencyStatus $residencyStatus,
        bool $isPrimary,
        ClockInterface $clock,
        ?Salutation $salutation = null,
        ?Height $height = null,
        ?Weight $weight = null,
    ): void {
        foreach ($this->members as $existing) {
            if ($existing->id()->equals($memberId)) {
                throw DuplicateMemberId::for($memberId);
            }
            if ($existing->code()->equals($memberCode)) {
                throw DuplicateMemberCode::for($memberCode);
            }
        }

        $member = new HouseholdMember(
            $memberId,
            $memberCode,
            $name,
            $dateOfBirth,
            $gender,
            $email,
            $phone,
            $residencyStatus,
            $isPrimary,
            $salutation,
            $height,
            $weight,
        );
        $member->attachToHousehold($this);
        $this->members->add($member);

        $this->recordThat(new MemberAddedToHousehold(
            $this->id,
            $memberId,
            $memberCode,
            $name,
            $isPrimary,
            $clock->now(),
        ));
    }

    public function removeMember(MemberId $memberId, ClockInterface $clock): void
    {
        $removed = null;
        foreach ($this->members as $existing) {
            if ($existing->id()->equals($memberId)) {
                $removed = $existing;
                break;
            }
        }

        if ($removed === null) {
            throw MemberNotFound::inHousehold($this->id, $memberId);
        }

        $this->members->removeElement($removed);
        $this->recordThat(new MemberRemovedFromHousehold($this->id, $memberId, $clock->now()));
    }

    /**
     * 8 parameters: identity (memberId, name, dateOfBirth, gender), the
     * clock, and the three independently-optional LRA-205 measurement
     * fields (salutation, height, weight). Each is a distinct, unrelated
     * attribute — bundling them into a parameter object would be a
     * meaningless grouping rather than a domain concept, and this mirrors
     * {@see self::register()}'s existing wide constructor for the same
     * reason.
     */
    public function updateMemberProfile( // NOSONAR php:S107 — see docblock
        MemberId $memberId,
        PersonName $name,
        DateOfBirth $dateOfBirth,
        Gender $gender,
        ClockInterface $clock,
        ?Salutation $salutation = null,
        ?Height $height = null,
        ?Weight $weight = null,
    ): void {
        $member = $this->memberById($memberId);
        $this->assertNotMerged($member);

        $heightChanged = !self::optionalEquals(
            $member->height(),
            $height,
            static fn(Height $a, Height $b): bool => $a->equals($b),
        );
        $weightChanged = !self::optionalEquals(
            $member->weight(),
            $weight,
            static fn(Weight $a, Weight $b): bool => $a->equals($b),
        );

        $changed = !$member->name()->equals($name)
            || !$member->dateOfBirth()->equals($dateOfBirth)
            || $member->gender() !== $gender
            || $member->salutation() !== $salutation
            || $heightChanged
            || $weightChanged;

        if (!$changed) {
            return;
        }

        $member->rename($name);
        $member->updateDateOfBirth($dateOfBirth);
        $member->updateGender($gender);
        $member->updateSalutation($salutation);
        $member->updateMeasurements($height, $weight);

        $this->recordThat(new MemberProfileUpdated($this->id, $memberId, $clock->now()));
    }

    public function updateMemberContact(
        MemberId $memberId,
        ?EmailAddress $email,
        ?PhoneNumber $phone,
        ClockInterface $clock,
    ): void {
        $member = $this->memberById($memberId);
        $this->assertNotMerged($member);

        $currentEmail = $member->email();
        $currentPhone = $member->phone();

        $emailChanged = !self::optionalEquals(
            $currentEmail,
            $email,
            static fn(EmailAddress $a, EmailAddress $b): bool => $a->equals($b),
        );
        $phoneChanged = !self::optionalEquals(
            $currentPhone,
            $phone,
            static fn(PhoneNumber $a, PhoneNumber $b): bool => $a->equals($b),
        );

        if (!$emailChanged && !$phoneChanged) {
            return;
        }

        $member->updateContact($email, $phone);
        $this->recordThat(new MemberContactUpdated(
            $this->id,
            $memberId,
            $email,
            $phone,
            $clock->now(),
        ));
    }

    public function updateAddress(Address $address, ClockInterface $clock): void
    {
        if ($this->address->equals($address)) {
            return;
        }

        $this->address = $address;
        $this->recordThat(new HouseholdAddressUpdated($this->id, $address, $clock->now()));
    }

    public function setResidencyStatus(
        MemberId $memberId,
        ResidencyStatus $status,
        DateTimeImmutable $effectiveFrom,
        ClockInterface $clock,
        ?string $reason = null,
    ): void {
        $member = $this->memberById($memberId);
        $this->assertNotMerged($member);

        if ($member->residencyStatus() === $status) {
            return;
        }

        $member->changeResidency($status);
        $this->recordThat(new MemberResidencyChanged(
            $this->id,
            $memberId,
            $status,
            $effectiveFrom,
            $clock->now(),
            $reason,
        ));
    }

    public function deactivateMember(
        MemberId $memberId,
        string $reason,
        ClockInterface $clock,
    ): void {
        $member = $this->memberById($memberId);
        $this->assertNotMerged($member);

        if (!$member->isActive()) {
            return;
        }

        $now = $clock->now();
        $member->deactivate($reason, $now);
        $this->recordThat(new MemberDeactivated($this->id, $memberId, $reason, $now));
    }

    public function reactivateMember(MemberId $memberId, ClockInterface $clock): void
    {
        $member = $this->memberById($memberId);
        $this->assertNotMerged($member);

        if ($member->isActive()) {
            return;
        }

        $member->reactivate();
        $this->recordThat(new MemberReactivated($this->id, $memberId, $clock->now()));
    }

    /**
     * Merges the duplicate member identified by $duplicateId into the
     * survivor member identified by $survivorId (owned by a possibly
     * different {@see Household} aggregate, identified by
     * $survivorHouseholdId) — LRA-208.
     *
     * Called on the duplicate's owning aggregate: $this must be the
     * household that owns $duplicateId. The survivor-side invariant (the
     * survivor exists and is not itself merged) is asserted separately by
     * {@see MemberMergePolicy} against the survivor's aggregate before
     * this method runs, since a single aggregate transaction cannot span
     * two Household instances.
     *
     * @throws CannotMergeMemberIntoItself when $duplicateId equals $survivorId
     * @throws MemberAlreadyMerged when the duplicate is already merged
     * @throws MemberNotFound when $duplicateId does not belong to $this household
     */
    public function mergeMemberInto(
        MemberId $duplicateId,
        HouseholdId $survivorHouseholdId,
        MemberId $survivorId,
        ClockInterface $clock,
    ): void {
        if ($duplicateId->equals($survivorId)) {
            throw CannotMergeMemberIntoItself::for($duplicateId);
        }

        $duplicate = $this->memberById($duplicateId);
        $this->assertNotMerged($duplicate);

        $now = $clock->now();
        $duplicate->markMergedInto($survivorId, $now);

        $this->recordThat(new MemberMergedInto(
            $this->id,
            $duplicateId,
            $survivorHouseholdId,
            $survivorId,
            $duplicate->email(),
            $duplicate->phone(),
            $now,
        ));
    }

    /**
     * Fills the survivor's blank email/phone from the values carried on
     * {@see MemberMergedInto} — the legacy merge's contact gap-fill
     * (LRA-208). Delegates to {@see self::updateMemberContact()} so the
     * existing {@see MemberContactUpdated} event is reused and a no-op
     * (nothing blank, or nothing supplied) stays silent.
     */
    public function fillMemberContactGaps(
        MemberId $memberId,
        ?EmailAddress $email,
        ?PhoneNumber $phone,
        ClockInterface $clock,
    ): void {
        $member = $this->memberById($memberId);

        $this->updateMemberContact(
            $memberId,
            $member->email() ?? $email,
            $member->phone() ?? $phone,
            $clock,
        );
    }

    /**
     * Attaches (or replaces) a member's profile photo. When a previous
     * photo existed, its storage key is released via
     * {@see MemberPhotoReleased} in the same call so the superseded file
     * is cleaned up — the caller never has to orchestrate the two steps
     * itself.
     */
    public function attachMemberPhoto(MemberId $memberId, ProfilePhoto $photo, ClockInterface $clock): void
    {
        $member = $this->memberById($memberId);
        $previous = $member->photo();

        $member->attachPhoto($photo);
        $this->recordThat(new MemberPhotoAttached($this->id, $memberId, $photo->storageKey, $clock->now()));

        if ($previous !== null) {
            $this->recordThat(new MemberPhotoReleased($previous->storageKey, $clock->now()));
        }
    }

    /**
     * Removes a member's profile photo. A no-op when the member has none.
     */
    public function removeMemberPhoto(MemberId $memberId, ClockInterface $clock): void
    {
        $member = $this->memberById($memberId);
        $previous = $member->photo();

        if ($previous === null) {
            return;
        }

        $member->removePhoto();
        $now = $clock->now();
        $this->recordThat(new MemberPhotoRemoved($this->id, $memberId, $now));
        $this->recordThat(new MemberPhotoReleased($previous->storageKey, $now));
    }

    private function memberById(MemberId $id): HouseholdMember
    {
        foreach ($this->members as $member) {
            if ($member->id()->equals($id)) {
                return $member;
            }
        }

        throw MemberNotFound::inHousehold($this->id, $id);
    }

    /**
     * @throws MemberAlreadyMerged when $member has already been merged into
     *                             a survivor — every mutator on a merged
     *                             member is refused (LRA-208).
     */
    private function assertNotMerged(HouseholdMember $member): void
    {
        if ($member->isMerged()) {
            throw MemberAlreadyMerged::for($member->id());
        }
    }

    /**
     * @template T of object
     *
     * @param T|null $a
     * @param T|null $b
     * @param callable(T, T): bool $equals
     */
    private static function optionalEquals(?object $a, ?object $b, callable $equals): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        if ($a === null || $b === null) {
            return false;
        }

        return $equals($a, $b);
    }
}
