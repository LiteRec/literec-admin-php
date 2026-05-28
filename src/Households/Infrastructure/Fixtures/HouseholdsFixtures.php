<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Fixtures;

use App\Shared\Infrastructure\Fixtures\FixtureEnv;
use App\Households\Application\Command\AddMemberToHousehold;
use App\Households\Application\Command\ChangeMemberResidency;
use App\Households\Application\Command\DeactivateMember;
use App\Households\Application\Command\RegisterHousehold;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\ResidencyStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Generator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Seeds the Households bounded context by dispatching application-layer
 * commands (RegisterHousehold, AddMemberToHousehold, ChangeMemberResidency,
 * DeactivateMember). Writes flow through the command bus — no direct
 * aggregate construction, no EntityManager, no repository calls.
 *
 * The fixture loads four curated households plus a Faker-generated bulk
 * batch. The curated set exercises the non-trivial state transitions
 * (residency change, deactivation) so integration/functional tests have
 * predictable scenarios to assert against.
 */
final class HouseholdsFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    private const DEFAULT_POSTAL_CODE = '94000';
    private const DEFAULT_BULK_COUNT = 25;
    private const MAX_BULK_COUNT = 1000;
    private const DEFAULT_SEED = 1;
    // Offset between the per-iteration seed used by UsersFixtures and
    // the one used here. The two contexts share the FIXTURE_SEED but
    // keep their Faker sequences disjoint so the same surname does not
    // appear at the same index in both fixtures.
    private const SEED_OFFSET = 10_000;

    /** @var list<string> */
    private const US_STATE_ABBREVIATIONS = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
        'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
        'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
        'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
        'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
    ];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly Generator $faker,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Re-seed at the entry point so the Faker sequence inside this
        // fixture is reproducible even when framework code between
        // fixtures (or between curated/bulk phases here) consumes
        // process-wide mt_rand state via random_int / mt_rand calls.
        $this->faker->seed($this->seedValue());

        $this->loadCurated();
        $this->loadBulk();
    }

    public function getDependencies(): array
    {
        // String reference avoids a cross-context import (Deptrac forbids
        // App\Households\* depending on App\Users\*); the fixtures loader
        // resolves dependencies by FQCN string.
        return ['App\\Users\\Infrastructure\\Fixtures\\UsersFixtures'];
    }

    public static function getGroups(): array
    {
        return ['dev', 'test', 'demo'];
    }

    private function loadCurated(): void
    {
        $this->loadSinglePersonHousehold();
        $this->loadMultiMemberFamily();
        $this->loadSeniorWithResidencyChange();
        $this->loadInactiveMemberHousehold();
    }

    private function loadSinglePersonHousehold(): void
    {
        $this->registerHousehold(new RegisterHousehold(
            householdName: 'Smith Single',
            firstName: 'Alice',
            lastName: 'Smith',
            middleName: null,
            suffix: null,
            dobIso: '1985-04-12',
            genderCode: Gender::Female->value,
            email: 'alice.smith@example.com',
            phone: '+1-555-0101',
            residencyStatusCode: ResidencyStatus::Resident->value,
            memberCode: null,
            street: '123 Oak St',
            unit: null,
            city: 'Anytown',
            state: 'CA',
            postalCode: self::DEFAULT_POSTAL_CODE,
            country: 'US',
        ));
    }

    private function loadMultiMemberFamily(): void
    {
        $familyId = $this->registerHousehold(new RegisterHousehold(
            householdName: 'Jones Family',
            firstName: 'Bob',
            lastName: 'Jones',
            middleName: null,
            suffix: null,
            dobIso: '1978-09-30',
            genderCode: Gender::Male->value,
            email: 'bob.jones@example.com',
            phone: '+1-555-0102',
            residencyStatusCode: ResidencyStatus::Resident->value,
            memberCode: null,
            street: '456 Maple Ave',
            unit: 'Apt 3',
            city: 'Anytown',
            state: 'CA',
            postalCode: self::DEFAULT_POSTAL_CODE,
            country: 'US',
        ));
        $this->addMember(
            $familyId,
            'Carol',
            'Jones',
            '1980-02-14',
            Gender::Female,
            'carol.jones@example.com',
            '+1-555-0103',
            ResidencyStatus::Resident,
        );
        $this->addMember(
            $familyId,
            'Dylan',
            'Jones',
            '2013-06-21',
            Gender::Male,
            'dylan.jones@example.com',
            '+1-555-0104',
            ResidencyStatus::Resident,
        );
        $this->addMember(
            $familyId,
            'Erin',
            'Jones',
            '2017-11-04',
            Gender::Female,
            'erin.jones@example.com',
            '+1-555-0105',
            ResidencyStatus::Resident,
        );
    }

    private function loadSeniorWithResidencyChange(): void
    {
        $seniorId = $this->registerHousehold(new RegisterHousehold(
            householdName: 'Miller Senior',
            firstName: 'Frank',
            lastName: 'Miller',
            middleName: null,
            suffix: null,
            dobIso: '1947-03-18',
            genderCode: Gender::Male->value,
            email: 'frank.miller@example.com',
            phone: '+1-555-0106',
            residencyStatusCode: ResidencyStatus::Resident->value,
            memberCode: null,
            street: '789 Elm Rd',
            unit: null,
            city: 'Anytown',
            state: 'CA',
            postalCode: self::DEFAULT_POSTAL_CODE,
            country: 'US',
        ));
        $gailId = $this->addMember(
            $seniorId,
            'Gail',
            'Miller',
            '1950-07-22',
            Gender::Female,
            'gail.miller@example.com',
            '+1-555-0107',
            ResidencyStatus::Resident,
        );
        $this->dispatch(new ChangeMemberResidency(
            householdId: $seniorId->value,
            memberId: $gailId->value,
            residencyStatusCode: ResidencyStatus::Member->value,
            effectiveFromIso: '2025-01-15',
            reason: 'Membership purchased',
        ));
    }

    private function loadInactiveMemberHousehold(): void
    {
        $inactiveId = $this->registerHousehold(new RegisterHousehold(
            householdName: 'Brown Inactive',
            firstName: 'Henry',
            lastName: 'Brown',
            middleName: null,
            suffix: null,
            dobIso: '1990-12-05',
            genderCode: Gender::Male->value,
            email: 'henry.brown@example.com',
            phone: '+1-555-0108',
            residencyStatusCode: ResidencyStatus::Resident->value,
            memberCode: null,
            street: '321 Pine Ln',
            unit: null,
            city: 'Anytown',
            state: 'CA',
            postalCode: self::DEFAULT_POSTAL_CODE,
            country: 'US',
        ));
        $irisId = $this->addMember(
            $inactiveId,
            'Iris',
            'Brown',
            '1992-08-19',
            Gender::Female,
            'iris.brown@example.com',
            '+1-555-0109',
            ResidencyStatus::Resident,
        );
        $this->dispatch(new DeactivateMember(
            householdId: $inactiveId->value,
            memberId: $irisId->value,
            reason: 'Moved out of household',
        ));
    }

    private function loadBulk(): void
    {
        $baseSeed = $this->seedValue();
        $bulkCount = $this->bulkCount();
        $genders = [Gender::Female, Gender::Male, Gender::Other, Gender::Unspecified];
        $residencies = [ResidencyStatus::Resident, ResidencyStatus::NonResident, ResidencyStatus::Member];

        for ($i = 1; $i <= $bulkCount; $i++) {
            // Re-seed per iteration so Faker output is independent of
            // however much mt_rand state the surrounding framework
            // consumed since the previous iteration. The counter prefix
            // in email and household name guarantees uniqueness; the
            // SEED_OFFSET keeps Households' sequence separate from
            // UsersFixtures' so identical surnames don't appear across
            // contexts at the same index.
            $this->faker->seed($baseSeed + self::SEED_OFFSET + $i);
            $surname = $this->faker->lastName();
            $headFirst = $this->faker->firstName();
            $householdName = sprintf('%s Household %04d', $surname, $i);
            $headGender = $genders[$this->faker->numberBetween(0, 3)];
            $headResidency = $residencies[$this->faker->numberBetween(0, 2)];
            $headEmail = sprintf('head-%04d-%s', $i, $this->faker->safeEmail());

            $householdId = $this->registerHousehold(new RegisterHousehold(
                householdName: $householdName,
                firstName: $headFirst,
                lastName: $surname,
                middleName: null,
                suffix: null,
                dobIso: $this->faker->dateTimeBetween('-90 years', '-18 years')->format('Y-m-d'),
                genderCode: $headGender->value,
                email: $headEmail,
                phone: $this->faker->numerify('+1-###-###-####'),
                residencyStatusCode: $headResidency->value,
                memberCode: null,
                street: $this->faker->streetAddress(),
                unit: null,
                city: $this->faker->city(),
                state: $this->randomState(),
                postalCode: $this->faker->postcode(),
                country: 'US',
            ));

            $additionalMembers = $this->faker->numberBetween(0, 4);
            for ($m = 1; $m <= $additionalMembers; $m++) {
                $memberEmail = sprintf('member-%04d-%d-%s', $i, $m, $this->faker->safeEmail());
                $this->addMember(
                    householdId: $householdId,
                    firstName: $this->faker->firstName(),
                    lastName: $surname,
                    dobIso: $this->faker->dateTimeBetween('-90 years', '-1 years')->format('Y-m-d'),
                    gender: $genders[$this->faker->numberBetween(0, 3)],
                    email: $memberEmail,
                    phone: $this->faker->numerify('+1-###-###-####'),
                    residency: $residencies[$this->faker->numberBetween(0, 2)],
                );
            }
        }
    }

    private function registerHousehold(RegisterHousehold $command): HouseholdId
    {
        $envelope = $this->commandBus->dispatch($command);

        return $this->handledResult($envelope, HouseholdId::class);
    }

    private function addMember(
        HouseholdId $householdId,
        string $firstName,
        string $lastName,
        string $dobIso,
        Gender $gender,
        string $email,
        string $phone,
        ResidencyStatus $residency,
    ): MemberId {
        $envelope = $this->commandBus->dispatch(new AddMemberToHousehold(
            householdId: $householdId->value,
            firstName: $firstName,
            lastName: $lastName,
            middleName: null,
            suffix: null,
            dobIso: $dobIso,
            genderCode: $gender->value,
            email: $email,
            phone: $phone,
            residencyStatusCode: $residency->value,
            memberCode: null,
            isPrimary: false,
        ));

        return $this->handledResult($envelope, MemberId::class);
    }

    private function dispatch(object $command): void
    {
        $this->commandBus->dispatch($command);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    private function handledResult(Envelope $envelope, string $type): object
    {
        $stamp = $envelope->last(HandledStamp::class);
        if (!$stamp instanceof HandledStamp) {
            throw FixtureDispatchFailed::missingHandledStamp($type);
        }

        $result = $stamp->getResult();
        if (!$result instanceof $type) {
            throw FixtureDispatchFailed::unexpectedResultType($type, get_debug_type($result));
        }

        return $result;
    }

    private function randomState(): string
    {
        $max = count(self::US_STATE_ABBREVIATIONS) - 1;

        return self::US_STATE_ABBREVIATIONS[$this->faker->numberBetween(0, $max)];
    }

    private function bulkCount(): int
    {
        return FixtureEnv::bulkCount('FIXTURE_HOUSEHOLD_COUNT', self::DEFAULT_BULK_COUNT, self::MAX_BULK_COUNT);
    }

    private function seedValue(): int
    {
        return FixtureEnv::seed(self::DEFAULT_SEED);
    }
}
