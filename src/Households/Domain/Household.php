<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\Event\HouseholdRegistered;
use App\Households\Domain\Event\MemberAddedToHousehold;
use App\Households\Domain\Event\MemberAnonymized;
use App\Households\Domain\Event\HouseholdAddressUpdated;
use App\Households\Domain\Event\MemberPhotoReleased;
use App\Households\Domain\Event\MemberRemovedFromHousehold;
use App\Households\Domain\Event\MemberSplitOff;
use App\Households\Domain\Exception\DuplicateMemberCode;
use App\Households\Domain\Exception\DuplicateMemberId;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberIsAnonymized;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Exception\SplitSelectionEmpty;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\AnonymizedProfile;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberContact;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberProfile;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Domain\ValueObject\TransactionReferences;
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
 * {@see MemberInHousehold::updateContact()}) so contact-only edits —
 * including "remove email", "remove phone" — can be expressed without
 * re-asking for other profile fields. The command DTOs in the Application
 * layer ({@see \App\Households\Application\Command\RegisterHousehold},
 * {@see \App\Households\Application\Command\AddMemberToHousehold}) currently
 * require both at registration/add time because the legacy "view users" UI
 * never created a member without contact info; LRA-43 may relax that.
 *
 * Household methods change household state or the member set; per-member
 * state changes go through {@see self::member()}.
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
        MemberProfile $primaryMemberProfile,
        MemberContact $primaryMemberContact,
        ResidencyStatus $primaryMemberResidency,
        ClockInterface $clock,
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
            $primaryMemberProfile,
            $primaryMemberContact,
            $primaryMemberResidency,
            true,
            $household,
        );
        $household->members->add($primary);
        $household->recordThat(new MemberAddedToHousehold(
            $id,
            $primaryMemberId,
            $primaryMemberCode,
            $primaryMemberProfile->name,
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
        MemberProfile $profile,
        MemberContact $contact,
        ResidencyStatus $residencyStatus,
        bool $isPrimary,
        ClockInterface $clock,
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
            $profile,
            $contact,
            $residencyStatus,
            $isPrimary,
            $this,
        );
        $this->members->add($member);

        $this->recordThat(new MemberAddedToHousehold(
            $this->id,
            $memberId,
            $memberCode,
            $profile->name,
            $isPrimary,
            $clock->now(),
        ));
    }

    public function removeMember(MemberId $memberId, ClockInterface $clock): void
    {
        $removed = $this->unmergedMemberById($memberId);

        $this->members->removeElement($removed);
        $this->recordThat(new MemberRemovedFromHousehold($this->id, $memberId, $clock->now()));
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
     * Unlike {@see MemberInHousehold::deactivate()}, a second call is
     * refused rather than silently ignored: an operator retrying an
     * anonymize action must know nothing happened, not believe it
     * succeeded again.
     *
     * @throws MemberNotFound when $memberId does not belong to this household
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws MemberIsAnonymized when the member has already been anonymized
     */
    public function anonymizeMember(MemberId $memberId, AnonymizedProfile $profile, ClockInterface $clock): void
    {
        $member = $this->unmergedMemberById($memberId);

        if ($member->lifecycle()->isAnonymized()) {
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
        $source = $this->unmergedMemberById($sourceMemberId);

        if ($transactions->count() === 0) {
            throw SplitSelectionEmpty::forMember($sourceMemberId);
        }

        $sourceProfile = $source->profile();
        $this->addMember(
            $newMemberId,
            $newMemberCode,
            MemberProfile::of($name, $sourceProfile->dateOfBirth, $sourceProfile->gender),
            MemberContact::of($email, $phone),
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
     * The single write handle for every per-member state change: household
     * methods change household state or the member set, and everything
     * scoped to one member — profile, contact, residency, lifecycle,
     * merge, photo, and household-sharing — goes through the returned
     * {@see MemberInHousehold}. It wraps the live {@see HouseholdMember}
     * entity and records every event into this aggregate's own buffer, so
     * {@see self::releaseEvents()} is unaffected.
     *
     * @throws MemberNotFound when $memberId does not belong to this household
     */
    public function member(MemberId $memberId): MemberInHousehold
    {
        return new MemberInHousehold($this->memberById($memberId), $this->id, $this->recordThat(...));
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
     * @throws MemberNotFound when $id does not belong to this household
     * @throws MemberAlreadyMerged when the member has already been merged into
     *                             a survivor — every mutator on a merged
     *                             member is refused (LRA-208).
     */
    private function unmergedMemberById(MemberId $id): HouseholdMember
    {
        $member = $this->memberById($id);

        if ($member->lifecycle()->isMerged()) {
            throw MemberAlreadyMerged::for($id);
        }

        return $member;
    }

    /**
     * Merged members are skipped: {@see MemberInHousehold::mergeInto()}
     * keeps the duplicate record in {@see self::$members} (marked merged,
     * never anonymized) so its history stays attributable, and that record
     * would otherwise block the household-level scrub forever.
     */
    private function everyMemberAnonymized(): bool
    {
        foreach ($this->members as $member) {
            $lifecycle = $member->lifecycle();
            if ($lifecycle->isMerged()) {
                continue;
            }

            if (!$lifecycle->isAnonymized()) {
                return false;
            }
        }

        return true;
    }
}
