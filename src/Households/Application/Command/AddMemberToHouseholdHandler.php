<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

use App\Households\Domain\Households;
use App\Households\Domain\IdentityGenerator;
use App\Households\Domain\MemberCodeAllocator;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class AddMemberToHouseholdHandler
{
    public function __construct(
        private readonly Households $households,
        private readonly IdentityGenerator $ids,
        private readonly MemberCodeAllocator $codes,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(AddMemberToHousehold $command): MemberId
    {
        $household = $this->households->findById(HouseholdId::fromString($command->householdId));

        $personName = PersonName::of(
            $command->firstName,
            $command->lastName,
            $command->middleName,
            $command->suffix,
        );
        $dob = DateOfBirth::parse($command->dobIso, $this->clock);
        $gender = Gender::from($command->genderCode);
        $residency = ResidencyStatus::from($command->residencyStatusCode);
        $email = EmailAddress::of($command->email);
        $phone = PhoneNumber::of($command->phone);

        $code = $command->memberCode !== null
            ? MemberCode::of($command->memberCode)
            : $this->codes->next();

        $memberId = $this->ids->nextMemberId();

        $household->addMember(
            $memberId,
            $code,
            $personName,
            $dob,
            $gender,
            $email,
            $phone,
            $residency,
            $command->isPrimary,
            $this->clock,
        );
        $this->households->save($household);

        foreach ($household->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }

        return $memberId;
    }
}
