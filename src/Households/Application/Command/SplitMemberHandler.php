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

/**
 * Splits transaction attribution off a source member onto a newly
 * created member in the same household (LRA-209).
 *
 * The in-memory {@see \App\Households\Domain\Household::splitMember()}
 * check that the source is not merged is a plain read against the
 * aggregate loaded at the top of this method — a concurrent
 * {@see MergeMembersHandler} merging the source could commit between
 * that read and this handler's write. {@see Households::lockUnmergedMember()}
 * closes that window the same way it does for merge (LRA-208 #193):
 * called inside the same command.bus transaction as {@see Households::save()},
 * immediately before it, it takes a row lock on the source and
 * re-asserts it is not merged under that lock, so a concurrent merge
 * either blocks until this transaction commits or is chosen as the
 * deadlock victim.
 */
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
        $sourceMemberId = MemberId::fromString($command->sourceMemberId);

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
            $sourceMemberId,
            $newMemberId,
            $code,
            $personName,
            $email,
            $phone,
            $transactions,
            $command->reason,
            $this->clock,
        );
        // Re-assert the "source is not merged" premise under a row lock, in
        // the same transaction as the write below — see the class docblock.
        $this->households->lockUnmergedMember($household->id(), $sourceMemberId);
        $this->households->save($household);

        foreach ($household->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }

        return $newMemberId;
    }
}
