<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\Event\MemberContactUpdated;
use App\Households\Domain\Event\MemberDeactivated;
use App\Households\Domain\Event\MemberMergedInto;
use App\Households\Domain\Event\MemberPhotoAttached;
use App\Households\Domain\Event\MemberPhotoReleased;
use App\Households\Domain\Event\MemberPhotoRemoved;
use App\Households\Domain\Event\MemberProfileUpdated;
use App\Households\Domain\Event\MemberReactivated;
use App\Households\Domain\Event\MemberResidencyChanged;
use App\Households\Domain\Event\MemberSharedWithHousehold;
use App\Households\Domain\Event\MemberSharingWithdrawn;
use App\Households\Domain\Exception\CannotMergeMemberIntoItself;
use App\Households\Domain\Exception\CannotShareWithHomeHousehold;
use App\Households\Domain\Exception\HouseholdAlreadyLinked;
use App\Households\Domain\Exception\InvariantViolation;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberIsAnonymized;
use App\Households\Domain\Exception\MemberNotAMinor;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberContact;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberProfile;
use App\Households\Domain\ValueObject\ProfilePhoto;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Closure;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Write handle for a single member of a {@see Household}, created by
 * {@see Household::member()}. Holds the live {@see HouseholdMember} entity —
 * never the cloned copy {@see Household::members()} returns — so every
 * mutation below is visible to the owning aggregate immediately.
 *
 * Every operation records its event through $recordEvent, a first-class
 * callable bound to the owning {@see Household}'s own
 * {@see AggregateRoot::recordThat()}, so {@see Household::releaseEvents()}
 * still returns every event this handle records; the handler's
 * fetch -> delegate -> save -> publish sequence is unchanged. The wrapped
 * {@see HouseholdMember} is never exposed — every state change stays behind
 * an intention-revealing method that enforces the aggregate's invariants.
 *
 * Not `readonly`: it exists purely to delegate mutation to the live
 * {@see HouseholdMember} it wraps.
 */
final class MemberInHousehold
{
    /**
     * @param Closure(object): void $recordEvent
     */
    public function __construct(
        private readonly HouseholdMember $member,
        private readonly HouseholdId $householdId,
        private readonly Closure $recordEvent,
    ) {
    }

    /**
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws MemberIsAnonymized when the member has been anonymized (LRA-212)
     */
    public function updateProfile(MemberProfile $profile, ClockInterface $clock): void
    {
        $member = $this->modifiable(MemberIsAnonymized::cannotBeModified(...));

        if ($member->profile()->equals($profile)) {
            return;
        }

        $member->updateProfile($profile);
        $this->record(new MemberProfileUpdated($this->householdId, $member->id(), $clock->now()));
    }

    /**
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws MemberIsAnonymized when the member has been anonymized (LRA-212)
     */
    public function updateContact(MemberContact $contact, ClockInterface $clock): void
    {
        $member = $this->modifiable(MemberIsAnonymized::cannotBeModified(...));

        if ($member->contact()->equals($contact)) {
            return;
        }

        $member->updateContact($contact);
        $this->record(new MemberContactUpdated(
            $this->householdId,
            $member->id(),
            $contact->email,
            $contact->phone,
            $clock->now(),
        ));
    }

    /**
     * Fills only the blank email/phone from $email/$phone, reusing
     * {@see self::updateContact()} so the existing {@see MemberContactUpdated}
     * event and its merged/anonymized guards are shared rather than
     * duplicated. A no-op stays silent.
     */
    public function fillContactGaps(?EmailAddress $email, ?PhoneNumber $phone, ClockInterface $clock): void
    {
        $this->updateContact($this->member->contact()->filledFrom($email, $phone), $clock);
    }

    /**
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws MemberIsAnonymized when the member has been anonymized (LRA-212)
     */
    public function changeResidency(
        ResidencyStatus $status,
        DateTimeImmutable $effectiveFrom,
        ClockInterface $clock,
        ?string $reason = null,
    ): void {
        $member = $this->modifiable(MemberIsAnonymized::cannotBeModified(...));

        if ($member->residencyStatus() === $status) {
            return;
        }

        $member->changeResidency($status);
        $this->record(new MemberResidencyChanged(
            $this->householdId,
            $member->id(),
            $status,
            $effectiveFrom,
            $clock->now(),
            $reason,
        ));
    }

    /**
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     */
    public function deactivate(string $reason, ClockInterface $clock): void
    {
        $member = $this->unmerged();

        if (!$member->lifecycle()->isActive) {
            return;
        }

        $now = $clock->now();
        $member->deactivate($reason, $now);
        $this->record(new MemberDeactivated($this->householdId, $member->id(), $reason, $now));
    }

    /**
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws MemberIsAnonymized when the member has been anonymized (LRA-212)
     */
    public function reactivate(ClockInterface $clock): void
    {
        $member = $this->modifiable(MemberIsAnonymized::cannotBeReactivated(...));

        if ($member->lifecycle()->isActive) {
            return;
        }

        $member->reactivate();
        $this->record(new MemberReactivated($this->householdId, $member->id(), $clock->now()));
    }

    /**
     * @throws CannotMergeMemberIntoItself when this member's id equals $survivorId
     * @throws MemberAlreadyMerged when this member has already been merged
     */
    public function mergeInto(HouseholdId $survivorHouseholdId, MemberId $survivorId, ClockInterface $clock): void
    {
        if ($this->member->id()->equals($survivorId)) {
            throw CannotMergeMemberIntoItself::for($this->member->id());
        }

        $member = $this->unmerged();

        $now = $clock->now();
        $member->markMergedInto($survivorId, $now);

        $contact = $member->contact();
        $this->record(new MemberMergedInto(
            $this->householdId,
            $member->id(),
            $survivorHouseholdId,
            $survivorId,
            $contact->email,
            $contact->phone,
            $now,
        ));
    }

    /**
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     */
    public function attachPhoto(ProfilePhoto $photo, ClockInterface $clock): void
    {
        $member = $this->unmerged();
        $previous = $member->photo();

        $member->replacePhoto($photo);
        $this->record(new MemberPhotoAttached($this->householdId, $member->id(), $photo->storageKey, $clock->now()));

        if ($previous !== null) {
            $this->record(new MemberPhotoReleased($previous->storageKey, $clock->now()));
        }
    }

    /**
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     */
    public function removePhoto(ClockInterface $clock): void
    {
        $member = $this->unmerged();
        $previous = $member->photo();

        if ($previous === null) {
            return;
        }

        $member->replacePhoto(null);
        $now = $clock->now();
        $this->record(new MemberPhotoRemoved($this->householdId, $member->id(), $now));
        $this->record(new MemberPhotoReleased($previous->storageKey, $now));
    }

    /**
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws InvariantViolation when the member is deactivated
     * @throws CannotShareWithHomeHousehold when $target is this household
     * @throws MemberNotAMinor when the member is not under 18 on the current date
     * @throws HouseholdAlreadyLinked when the member is already shared with $target
     */
    public function shareWithHousehold(HouseholdId $target, ClockInterface $clock): void
    {
        $member = $this->unmerged();

        if (!$member->lifecycle()->isActive) {
            throw InvariantViolation::with('An inactive member cannot be shared with another household.');
        }

        if ($target->equals($this->householdId)) {
            throw CannotShareWithHomeHousehold::for($member->id(), $target);
        }

        $now = $clock->now();
        if (!$member->profile()->dateOfBirth->isMinorOn($now)) {
            throw MemberNotAMinor::for($member->id());
        }

        if ($member->householdLinks()->includes($target)) {
            throw HouseholdAlreadyLinked::for($member->id(), $target);
        }

        $member->shareWith($target, $now);
        $this->record(new MemberSharedWithHousehold($this->householdId, $member->id(), $target, $now));
    }

    /**
     * No-op — mirroring the existing idempotent style of
     * {@see self::deactivate()} — when the member is not currently shared
     * with $target.
     */
    public function withdrawFromHousehold(HouseholdId $target, ClockInterface $clock): void
    {
        if (!$this->member->householdLinks()->includes($target)) {
            return;
        }

        $this->member->withdrawFrom($target);
        $this->record(new MemberSharingWithdrawn($this->householdId, $this->member->id(), $target, $clock->now()));
    }

    /**
     * @throws MemberAlreadyMerged when the member has already been merged into
     *                             a survivor — every mutator on a merged
     *                             member is refused (LRA-208).
     */
    private function unmerged(): HouseholdMember
    {
        if ($this->member->lifecycle()->isMerged()) {
            throw MemberAlreadyMerged::for($this->member->id());
        }

        return $this->member;
    }

    /**
     * @param callable(MemberId): MemberIsAnonymized $anonymizedExceptionFactory
     *        One of {@see MemberIsAnonymized}'s named constructors, bound to
     *        the call site so the thrown exception's message matches the
     *        refused operation.
     *
     * @throws MemberAlreadyMerged when the member has already been merged into another record
     * @throws MemberIsAnonymized when the member has been anonymized (LRA-212)
     */
    private function modifiable(callable $anonymizedExceptionFactory): HouseholdMember
    {
        $member = $this->unmerged();

        if ($member->lifecycle()->isAnonymized()) {
            throw $anonymizedExceptionFactory($member->id());
        }

        return $member;
    }

    private function record(object $event): void
    {
        ($this->recordEvent)($event);
    }
}
