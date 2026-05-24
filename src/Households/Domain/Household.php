<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\Event\HouseholdRegistered;
use App\Households\Domain\Event\MemberAddedToHousehold;
use App\Households\Domain\Event\HouseholdAddressUpdated;
use App\Households\Domain\Event\MemberContactUpdated;
use App\Households\Domain\Event\MemberDeactivated;
use App\Households\Domain\Event\MemberProfileUpdated;
use App\Households\Domain\Event\MemberReactivated;
use App\Households\Domain\Event\MemberRemovedFromHousehold;
use App\Households\Domain\Event\MemberResidencyChanged;
use App\Households\Domain\Exception\DuplicateMemberCode;
use App\Households\Domain\Exception\DuplicateMemberId;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\EmailAddress;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\PhoneNumber;
use App\Households\Domain\ValueObject\ResidencyStatus;
use DateTimeImmutable;
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
    /** @var list<HouseholdMember> */
    private array $members;
    private DateTimeImmutable $createdAt;

    private function __construct()
    {
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
    ): self {
        $household = new self();
        $household->id = $id;
        $household->name = $name;
        $household->address = $address;
        $household->members = [];
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
        );
        $household->members[] = $primary;
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
        return array_map(
            static fn(HouseholdMember $m): HouseholdMember => clone $m,
            $this->members,
        );
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
        );
        $this->members[] = $member;

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
        $next = [];
        $removed = false;
        foreach ($this->members as $existing) {
            if ($existing->id()->equals($memberId)) {
                $removed = true;
                continue;
            }
            $next[] = $existing;
        }

        if (!$removed) {
            throw MemberNotFound::inHousehold($this->id, $memberId);
        }

        $this->members = $next;
        $this->recordThat(new MemberRemovedFromHousehold($this->id, $memberId, $clock->now()));
    }

    public function updateMemberProfile(
        MemberId $memberId,
        PersonName $name,
        DateOfBirth $dateOfBirth,
        Gender $gender,
        ClockInterface $clock,
    ): void {
        $member = $this->memberById($memberId);

        $changed = !$member->name()->equals($name)
            || !$member->dateOfBirth()->equals($dateOfBirth)
            || $member->gender() !== $gender;

        if (!$changed) {
            return;
        }

        $member->rename($name);
        $member->updateDateOfBirth($dateOfBirth);
        $member->updateGender($gender);

        $this->recordThat(new MemberProfileUpdated($this->id, $memberId, $clock->now()));
    }

    public function updateMemberContact(
        MemberId $memberId,
        ?EmailAddress $email,
        ?PhoneNumber $phone,
        ClockInterface $clock,
    ): void {
        $member = $this->memberById($memberId);

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

        if ($member->isActive()) {
            return;
        }

        $member->reactivate();
        $this->recordThat(new MemberReactivated($this->id, $memberId, $clock->now()));
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
