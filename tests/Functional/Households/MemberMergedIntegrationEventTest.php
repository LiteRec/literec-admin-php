<?php

declare(strict_types=1);

namespace App\Tests\Functional\Households;

use App\Households\Application\Command\MergeMembers;
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
use App\Households\Integration\Event\MemberMerged;
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
 * {@see MemberMerged} integration event (LRA-208), mirroring
 * {@see \App\Tests\Functional\Catalog\Integration\LineSoldPostCommitTest}:
 * after a real merge commits, the async transport holds exactly one
 * MemberMerged envelope carrying the survivor/duplicate ids.
 */
#[Medium]
#[Group('database')]
final class MemberMergedIntegrationEventTest extends KernelTestCase
{
    private const string SURVIVOR_HOUSEHOLD_ID = '019571bf-5d55-7000-b500-00000000ea01';
    private const string SURVIVOR_MEMBER_ID    = '019571bf-5d55-7000-b500-00000000ea02';
    private const string DUPLICATE_HOUSEHOLD_ID = '019571bf-5d55-7000-b500-00000000ea03';
    private const string DUPLICATE_MEMBER_ID    = '019571bf-5d55-7000-b500-00000000ea04';

    #[Test]
    #[TestDox('After a merge commits, the async transport holds exactly one MemberMerged envelope.')]
    public function merge_publishes_exactly_one_member_merged_envelope(): void
    {
        self::bootKernel();
        $this->seedHouseholds();
        $transport = self::getAsyncTransport();

        $commandBus = self::getContainer()->get('command.bus');
        self::assertInstanceOf(MessageBusInterface::class, $commandBus);
        $commandBus->dispatch(new MergeMembers(
            self::SURVIVOR_HOUSEHOLD_ID,
            self::SURVIVOR_MEMBER_ID,
            self::DUPLICATE_MEMBER_ID,
        ));

        $envelopes = $transport->getSent();
        self::assertCount(1, $envelopes);
        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(MemberMerged::class, $message);
        self::assertSame(self::SURVIVOR_HOUSEHOLD_ID, $message->survivorHouseholdId);
        self::assertSame(self::SURVIVOR_MEMBER_ID, $message->survivorMemberId);
        self::assertSame(self::DUPLICATE_HOUSEHOLD_ID, $message->mergedHouseholdId);
        self::assertSame(self::DUPLICATE_MEMBER_ID, $message->mergedMemberId);
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

    private function seedHouseholds(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));

        $survivor = Household::register(
            HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID),
            HouseholdName::of('Merge Event Survivor'),
            Address::of('1 Test St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::SURVIVOR_MEMBER_ID),
            MemberCode::of('M000810'),
            MemberProfile::of(
                PersonName::of('Sam', 'Survivor'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $clock),
                Gender::Male,
            ),
            MemberContact::of(null, null),
            ResidencyStatus::Resident,
            $clock,
        );
        $repo->save($survivor);

        $duplicate = Household::register(
            HouseholdId::fromString(self::DUPLICATE_HOUSEHOLD_ID),
            HouseholdName::of('Merge Event Duplicate'),
            Address::of('2 Test St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::DUPLICATE_MEMBER_ID),
            MemberCode::of('M000811'),
            MemberProfile::of(
                PersonName::of('Dana', 'Duplicate'),
                DateOfBirth::of(new DateTimeImmutable('1991-02-02'), $clock),
                Gender::Female,
            ),
            MemberContact::of(null, null),
            ResidencyStatus::Resident,
            $clock,
        );
        $repo->save($duplicate);
    }
}
