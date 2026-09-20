<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Administrator;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\Exception\RankIsRetired;
use App\Administration\Domain\Exception\RankNotFound;
use App\Administration\Domain\Exception\SignInAccountAlreadyAnAdministrator;
use App\Administration\Domain\IdentityGenerator;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\SignInAccountId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class GrantAdministratorHandler
{
    use ReleasesAdministrationEvents;

    public function __construct(
        private readonly Administrators $administrators,
        private readonly Ranks $ranks,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly ActorAssembler $actors,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    private function eventBus(): MessageBusInterface // NOSONAR
    {
        return $this->eventBus;
    }

    /**
     * @throws RankNotFound when $command->rankId does not name an existing
     *         rank — administration_administrators carries no foreign key
     *         to administration_ranks (Rank is a separate aggregate; the
     *         project forbids Doctrine associations and foreign keys
     *         across an aggregate boundary), so this lookup is what stops
     *         an orphaned grant from ever being written.
     * @throws RankIsRetired when the rank exists but has already been
     *         retired. {@see Ranks::byId()} returns a retired rank
     *         unchanged — existence and liveness are different
     *         questions — so this handler checks both before granting.
     * @throws SignInAccountAlreadyAnAdministrator when the sign-in
     *         account already has an administrator record.
     */
    public function __invoke(GrantAdministrator $command): AdministratorId
    {
        $rankId = RankId::fromString($command->rankId);
        $rank = $this->ranks->byId($rankId);

        if ($rank->isRetired()) {
            throw RankIsRetired::for($rankId);
        }

        $signInAccountId = SignInAccountId::fromString($command->signInAccountId);

        if ($this->administrators->existsForSignInAccount($signInAccountId)) {
            throw SignInAccountAlreadyAnAdministrator::for($signInAccountId);
        }

        $actor = $this->actors->fromPrimitives($command->actorKind, $command->actorId);
        $id = $this->ids->nextAdministratorId();

        $administrator = Administrator::grant(
            $id,
            $signInAccountId,
            $rankId,
            $this->ids->nextTenureId(),
            $actor,
            $this->clock,
        );
        $this->administrators->add($administrator);

        $this->releaseAndDispatch($administrator);

        return $id;
    }
}
