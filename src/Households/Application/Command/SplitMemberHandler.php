<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

use App\Households\Domain\Households;
use App\Households\Domain\IdentityGenerator;
use App\Households\Domain\MemberCodeAllocator;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\TransactionReferences;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class SplitMemberHandler
{
    public function __construct(
        private readonly Households $households,
        private readonly IdentityGenerator $ids,
        private readonly MemberCodeAllocator $codes,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(SplitMember $command): MemberId
    {
        $household = $this->households->findById(HouseholdId::fromString($command->householdId));

        $personName = PersonName::of(
            $command->firstName,
            $command->lastName,
            $command->middleName,
            $command->suffix,
        );
        $email = $command->email !== null ? EmailAddress::of($command->email) : null;
        $phone = $command->phone !== null ? PhoneNumber::of($command->phone) : null;
        $transactions = TransactionReferences::fromStrings($command->transactionIds);

        $code = $command->memberCode !== null
            ? MemberCode::of($command->memberCode)
            : $this->codes->next();

        $newMemberId = $this->ids->nextMemberId();

        $household->splitMember(
            MemberId::fromString($command->sourceMemberId),
            $newMemberId,
            $code,
            $personName,
            $email,
            $phone,
            $transactions,
            $command->reason,
            $this->clock,
        );
        $this->households->save($household);

        foreach ($household->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }

        return $newMemberId;
    }
}
