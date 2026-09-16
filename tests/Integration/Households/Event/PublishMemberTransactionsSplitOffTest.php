<?php

declare(strict_types=1);

namespace App\Tests\Integration\Households\Event;

use App\Households\Application\Command\SplitMember;
use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberContact;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberProfile;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Integration\Event\MemberTransactionsSplitOff;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Pins down the post-commit publication contract for Households'
 * {@see MemberTransactionsSplitOff} integration event (LRA-209), mirroring
 * {@see \App\Tests\Functional\Households\MemberMergedIntegrationEventTest}:
 * after a real split commits, the async transport holds exactly one
 * MemberTransactionsSplitOff envelope carrying the source/new member ids
 * and the selected transaction ids.
 */
#[Medium]
#[Group('database')]
final class PublishMemberTransactionsSplitOffTest extends KernelTestCase
{
    private const string HOUSEHOLD_ID = '019571bf-5d55-7000-b500-00000000ee01';
    private const string SOURCE_ID    = '019571bf-5d55-7000-b500-00000000ee02';

    #[Test]
    #[TestDox('After a split commits, the async transport holds exactly one MemberTransactionsSplitOff envelope.')]
    public function split_publishes_exactly_one_envelope(): void
    {
        self::bootKernel();
        $this->seedHousehold();
        $transport = self::getAsyncTransport();

        $commandBus = self::getContainer()->get('command.bus');
        self::assertInstanceOf(MessageBusInterface::class, $commandBus);
        $commandBus->dispatch(new SplitMember(
            householdId: self::HOUSEHOLD_ID,
            sourceMemberId: self::SOURCE_ID,
            firstName: 'Bob',
            lastName: 'Smith',
            middleName: null,
            suffix: null,
            email: 'bob@example.com',
            phone: '5550002',
            transactionIds: ['txn-1', 'txn-2'],
            reason: null,
            memberCode: null,
        ));

        $envelopes = $transport->getSent();
        self::assertCount(1, $envelopes);
        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(MemberTransactionsSplitOff::class, $message);
        self::assertSame(self::HOUSEHOLD_ID, $message->householdId);
        self::assertSame(self::SOURCE_ID, $message->sourceMemberId);
        self::assertSame(['txn-1', 'txn-2'], $message->transactionIds);
    }

    private static function getAsyncTransport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(TransportInterface::class, $transport);
        self::assertInstanceOf(
            InMemoryTransport::class,
            $transport,
            'Test env must route async to in-memory:// — see config/packages/messenger.yaml when@test.',
        );
        $transport->reset();

        return $transport;
    }

    private function seedHousehold(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));

        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of('Split Event Family'),
            Address::of('1 Test St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::SOURCE_ID),
            MemberCode::of('M000820'),
            MemberProfile::of(
                PersonName::of('Sam', 'Source'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $clock),
                Gender::Male,
            ),
            MemberContact::of(null, null),
            ResidencyStatus::Resident,
            $clock,
        );
        $repo->save($household);
    }
}
