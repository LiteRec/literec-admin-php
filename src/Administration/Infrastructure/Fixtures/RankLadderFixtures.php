<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Fixtures;

use App\Administration\Application\Command\DefineRank;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\RankId;
use App\Shared\Infrastructure\Fixtures\FixtureReferenceRegistry;
use App\Shared\Infrastructure\Fixtures\HandledResult;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A fresh, generic 11-level rank ladder written from the job levels the
 * two legacy agencies' rank names reveal (LRA-267), not imported from
 * either legacy copy. Explicitly excludes the numbered variants Bellevue
 * carries (e.g. "Manager - 1", "CSR - 4") and its one rank named after an
 * individual — where a site genuinely needs a narrower grant at the same
 * job level, that is a second rank at the same seniority pointing at
 * different roles, never a new seniority step or a per-person rank.
 *
 * Dispatches DefineRank commands through the bus — same treatment as
 * {@see \App\Administration\Infrastructure\Fixtures\AdministrationRolesFixtures}.
 * The acting actor is Actor::system() (the installer/seed process).
 *
 * Roles are not granted here: this fixture seeds the ladder's shape only.
 *
 * Each rung's minted {@see RankId} is stashed on the fixtures reference
 * registry under {@see referenceKey()} (LRA-279) so
 * {@see AdministrationFixtures} can grant the seeded admin persona a real
 * rank without a second lookup path.
 */
final class RankLadderFixtures extends Fixture implements FixtureGroupInterface
{
    public const string REFERENCE_PREFIX = 'administration.rank.';
    /**
     * Seniority => rank name. Ordered ascending by seniority, i.e. most
     * senior first — 0 is the vendor-support level and carries no
     * implicit powers; it is an ordinary row whose roles are assigned
     * like any other (LRA-271 depends on that being true from the start).
     *
     * @var array<int, string>
     */
    public const array LADDER = [
        0 => 'Vendor Support',
        10 => 'System Administrator',
        20 => 'Director',
        30 => 'Manager',
        40 => 'Supervisor',
        50 => 'Program Coordinator',
        60 => 'Administrator',
        70 => 'Customer Support Representative',
        80 => 'Communications',
        90 => 'Reporting and Monitoring',
        100 => 'Volunteer',
    ];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly FixtureReferenceRegistry $references,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::LADDER as $seniority => $name) {
            $rankId = $this->dispatch(new DefineRank($name, $seniority, ActorKind::System->value));
            $this->references->set(self::referenceKey($name), $rankId);
        }
    }

    public static function getGroups(): array
    {
        return ['dev', 'test', 'demo'];
    }

    public static function referenceKey(string $rankName): string
    {
        return self::REFERENCE_PREFIX . $rankName;
    }

    private function dispatch(DefineRank $command): RankId
    {
        // DefineRank is not routed to an async transport, so dispatch()
        // runs the handler inline and any exception surfaces here.
        $envelope = $this->commandBus->dispatch($command);

        return HandledResult::from($envelope, RankId::class);
    }
}
