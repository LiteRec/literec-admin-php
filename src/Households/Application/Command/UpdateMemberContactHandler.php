<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Domain errors this handler can surface: {@see \App\Households\Domain\Exception\HouseholdNotFound},
 * {@see \App\Households\Domain\Exception\MemberNotFound},
 * {@see \App\Households\Domain\Exception\InvalidHouseholdId},
 * {@see \App\Households\Domain\Exception\InvalidMemberId},
 * {@see \App\Shared\Domain\Exception\InvalidEmailAddress},
 * {@see \App\Shared\Domain\Exception\InvalidPhoneNumber}.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class UpdateMemberContactHandler
{
    public function __construct(
        private readonly Households $households,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(UpdateMemberContact $command): void
    {
        $household = $this->households->findById(HouseholdId::fromString($command->householdId));
        $memberId = MemberId::fromString($command->memberId);

        $email = $command->email !== null ? EmailAddress::of($command->email) : null;
        $phone = $command->phone !== null ? PhoneNumber::of($command->phone) : null;

        $household->updateMemberContact($memberId, $email, $phone, $this->clock);
        $this->households->save($household);

        foreach ($household->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
