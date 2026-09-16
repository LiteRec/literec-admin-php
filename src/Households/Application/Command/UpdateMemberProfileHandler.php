<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\Height;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberProfile;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\Salutation;
use App\Households\Domain\ValueObject\Weight;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateMemberProfileHandler
{
    public function __construct(
        private readonly Households $households,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(UpdateMemberProfile $command): void
    {
        $household = $this->households->findById(HouseholdId::fromString($command->householdId));
        $memberId = MemberId::fromString($command->memberId);

        $name = PersonName::of(
            $command->firstName,
            $command->lastName,
            $command->middleName,
            $command->suffix,
            $command->nickname,
        );
        $dob = DateOfBirth::parse($command->dobIso, $this->clock);
        $gender = Gender::from($command->genderCode);
        $salutation = $command->salutationCode !== null ? Salutation::from($command->salutationCode) : null;
        $height = $command->heightInches !== null ? Height::ofInches($command->heightInches) : null;
        $weight = $command->weightPounds !== null ? Weight::ofPounds($command->weightPounds) : null;

        $profile = MemberProfile::of($name, $dob, $gender, $salutation, $height, $weight);
        $household->member($memberId)->updateProfile($profile, $this->clock);
        $this->households->save($household);

        foreach ($household->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
