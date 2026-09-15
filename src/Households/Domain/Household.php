<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\Event\HouseholdRegistered;
use App\Households\Domain\Event\MemberAddedToHousehold;
use App\Households\Domain\Event\MemberAnonymized;
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
use App\Households\Domain\Event\MemberSharedWithHousehold;
use App\Households\Domain\Event\MemberSharingWithdrawn;
use App\Households\Domain\Event\MemberSplitOff;
use App\Households\Domain\Exception\CannotMergeMemberIntoItself;
use App\Households\Domain\Exception\CannotShareWithHomeHousehold;
use App\Households\Domain\Exception\DuplicateMemberCode;
use App\Households\Domain\Exception\DuplicateMemberId;
use App\Households\Domain\Exception\HouseholdAlreadyLinked;
use App\Households\Domain\Exception\InvariantViolation;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberIsAnonymized;
use App\Households\Domain\Exception\MemberNotAMinor;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Exception\SplitSelectionEmpty;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\AnonymizedProfile;
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
use App\Households\Domain\ValueObject\TransactionReferences;
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
        $this->assertNotMerged($removed);

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
     *
     * @throws MemberNotFound when $memberId does not belong to this household
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws MemberIsAnonymized when the member has been anonymized (LRA-212)
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
        $this->assertNotAnonymized($member, MemberIsAnonymized::cannotBeModified(...));

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

    /**
     * @throws MemberNotFound when $memberId does not belong to this household
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws MemberIsAnonymized when the member has been anonymized (LRA-212)
     */
    public function updateMemberContact(
        MemberId $memberId,
        ?EmailAddress $email,
        ?PhoneNumber $phone,
        ClockInterface $clock,
    ): void {
        $member = $this->memberById($memberId);
        $this->assertNotMerged($member);
        $this->assertNotAnonymized($member, MemberIsAnonymized::cannotBeModified(...));

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

    /**
     * @throws MemberNotFound when $memberId does not belong to this household
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws MemberIsAnonymized when the member has been anonymized (LRA-212)
     */
    public function setResidencyStatus(
        MemberId $memberId,
        ResidencyStatus $status,
        DateTimeImmutable $effectiveFrom,
        ClockInterface $clock,
        ?string $reason = null,
    ): void {
        $member = $this->memberById($memberId);
        $this->assertNotMerged($member);
        $this->assertNotAnonymized($member, MemberIsAnonymized::cannotBeModified(...));

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

    /**
     * @throws MemberNotFound when $memberId does not belong to this household
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws MemberIsAnonymized when the member has been anonymized (LRA-212)
     */
    public function reactivateMember(MemberId $memberId, ClockInterface $clock): void
    {
        $member = $this->memberById($memberId);
        $this->assertNotMerged($member);
        $this->assertNotAnonymized($member, MemberIsAnonymized::cannotBeReactivated(...));

        if ($member->isActive()) {
            return;
        }

        $member->reactivate();
        $this->recordThat(new MemberReactivated($this->id, $memberId, $clock->now()));
    }

    /**
     * Irreversibly scrubs a member's PII (LRA-212): replaces name, date of
     * birth, gender, email, and phone with {@see AnonymizedProfile}'s
     * placeholder values, and deactivates the member in the same call. The
     * member keeps its MemberId and MemberCode so downstream references
     * (transaction history, residency history, future Memberships/
     * Transactions contexts) stay referentially intact — nothing is
     * deleted.
     *
     * When $memberId was the last non-anonymized member of this household,
     * the household's own name and address — which would otherwise still
     * identify the person — are replaced with the same placeholder set.
     *
     * Unlike {@see self::deactivateMember()}, a second call is refused
     * rather than silently ignored: an operator retrying an anonymize
     * action must know nothing happened, not believe it succeeded again.
     *
     * @throws MemberNotFound when $memberId does not belong to this household
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws MemberIsAnonymized when the member has already been anonymized
     */
    public function anonymizeMember(MemberId $memberId, AnonymizedProfile $profile, ClockInterface $clock): void
    {
        $member = $this->memberById($memberId);
        $this->assertNotMerged($member);

        if ($member->isAnonymized()) {
            throw MemberIsAnonymized::cannotBeAnonymizedAgain($memberId);
        }

        $now = $clock->now();
        $previousPhoto = $member->photo();
        $member->anonymize($profile, $now);
        $this->recordThat(new MemberAnonymized($this->id, $memberId, $now));

        if ($previousPhoto !== null) {
            $this->recordThat(new MemberPhotoReleased($previousPhoto->storageKey, $now));
        }

        if ($this->everyMemberAnonymized()) {
            $this->name = $profile->householdName;
            $this->address = $profile->address;
        }
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
     * Splits transaction attribution off the source member onto a newly
     * created member in this same household (LRA-209): the source is
     * never mutated or deleted. The new member copies date of birth,
     * gender, and residency status from the source (name and contact are
     * entered fresh, since the two are distinct people) and is added via
     * {@see self::addMember()} so the existing duplicate-id/duplicate-code
     * guard and {@see Event\MemberAddedToHousehold} are reused rather than
     * duplicated. Splitting a deactivated source is allowed — history
     * clean-up is a legitimate reason to touch an archived record.
     *
     * Physical reassignment of the selected transactions happens
     * out-of-process in the (not-yet-existing) Transactions context,
     * which will subscribe to the published
     * {@see \App\Households\Integration\Event\MemberTransactionsSplitOff}
     * integration event.
     *
     * @throws MemberNotFound when $sourceMemberId does not belong to this household
     * @throws MemberAlreadyMerged when the source is already merged into another member
     * @throws SplitSelectionEmpty when $transactions is empty
     * @throws DuplicateMemberId when $newMemberId already exists in this household
     * @throws DuplicateMemberCode when $newMemberCode already exists in this household
     */
    public function splitMember( // NOSONAR php:S107 — mirrors addMember()'s equally wide constructor
        MemberId $sourceMemberId,
        MemberId $newMemberId,
        MemberCode $newMemberCode,
        PersonName $name,
        ?EmailAddress $email,
        ?PhoneNumber $phone,
        TransactionReferences $transactions,
        ?string $reason,
        ClockInterface $clock,
    ): void {
        $source = $this->memberById($sourceMemberId);
        $this->assertNotMerged($source);

        if ($transactions->count() === 0) {
            throw SplitSelectionEmpty::forMember($sourceMemberId);
        }

        $this->addMember(
            $newMemberId,
            $newMemberCode,
            $name,
            $source->dateOfBirth(),
            $source->gender(),
            $email,
            $phone,
            $source->residencyStatus(),
            false,
            $clock,
        );

        $this->recordThat(new MemberSplitOff(
            $this->id,
            $sourceMemberId,
            $newMemberId,
            $newMemberCode,
            $transactions,
            $reason,
            $clock->now(),
        ));
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
        $this->assertNotMerged($member);
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
        $this->assertNotMerged($member);
        $previous = $member->photo();

        if ($previous === null) {
            return;
        }

        $member->removePhoto();
        $now = $clock->now();
        $this->recordThat(new MemberPhotoRemoved($this->id, $memberId, $now));
        $this->recordThat(new MemberPhotoReleased($previous->storageKey, $now));
    }

    /**
     * Shares $memberId — who must be a minor and must belong to this (their
     * home) household — with another household (LRA-210, e.g. shared
     * custody). The member's identity data keeps a single owner (this
     * aggregate); $target is loaded only by the caller to confirm it
     * exists, never mutated here — a single transaction touches at most one
     * aggregate.
     *
     * @throws MemberNotFound when $memberId does not belong to this household
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws InvariantViolation when the member is deactivated
     * @throws CannotShareWithHomeHousehold when $target is this household
     * @throws MemberNotAMinor when the member is not under 18 on the current date
     * @throws HouseholdAlreadyLinked when the member is already shared with $target
     */
    public function shareMemberWithHousehold(
        MemberId $memberId,
        HouseholdId $target,
        ClockInterface $clock,
    ): void {
        $member = $this->memberById($memberId);
        $this->assertNotMerged($member);

        if (!$member->isActive()) {
            throw InvariantViolation::with('An inactive member cannot be shared with another household.');
        }

        if ($target->equals($this->id)) {
            throw CannotShareWithHomeHousehold::for($memberId, $target);
        }

        $now = $clock->now();
        if (!$member->dateOfBirth()->isMinorOn($now)) {
            throw MemberNotAMinor::for($memberId);
        }

        if ($member->isSharedWith($target)) {
            throw HouseholdAlreadyLinked::for($memberId, $target);
        }

        $member->shareWith($target, $now);
        $this->recordThat(new MemberSharedWithHousehold($this->id, $memberId, $target, $now));
    }

    /**
     * Withdraws a previously-created share (LRA-210). A no-op — mirroring
     * the existing idempotent style of {@see self::deactivateMember()} —
     * when the member is not currently shared with $target.
     *
     * @throws MemberNotFound when $memberId does not belong to this household
     */
    public function withdrawMemberFromHousehold(
        MemberId $memberId,
        HouseholdId $target,
        ClockInterface $clock,
    ): void {
        $member = $this->memberById($memberId);

        if (!$member->isSharedWith($target)) {
            return;
        }

        $member->withdrawFrom($target);
        $this->recordThat(new MemberSharingWithdrawn($this->id, $memberId, $target, $clock->now()));
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
     * @param callable(MemberId): MemberIsAnonymized $exceptionFactory One of
     *        {@see MemberIsAnonymized}'s named constructors, bound to the
     *        call site so the thrown exception's message matches the
     *        refused operation.
     *
     * @throws MemberIsAnonymized when $member has been anonymized (LRA-212)
     */
    private function assertNotAnonymized(HouseholdMember $member, callable $exceptionFactory): void
    {
        if ($member->isAnonymized()) {
            throw $exceptionFactory($member->id());
        }
    }

    /**
     * Merged members are skipped: {@see self::mergeMemberInto()} keeps the
     * duplicate record in {@see self::$members} (marked merged, never
     * anonymized) so its history stays attributable, and that record
     * would otherwise block the household-level scrub forever.
     */
    private function everyMemberAnonymized(): bool
    {
        foreach ($this->members as $member) {
            if ($member->isMerged()) {
                continue;
            }

            if (!$member->isAnonymized()) {
                return false;
            }
        }

        return true;
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
