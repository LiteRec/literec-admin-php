<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Console;

use App\Administration\Application\Command\GrantAdministrator;
use App\Administration\Domain\Exception\AdministrationDomainException;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\AdministratorId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Thin adapter that decodes CLI input into a {@see GrantAdministrator}
 * command and dispatches it via the command bus. Contains zero business
 * logic. Used to bootstrap an installation's first administrator — the
 * one grant that has no acting administrator to name yet — so it always
 * dispatches with the system actor, mirroring {@see \App\Users\Infrastructure\Console\CreateUserCommand}
 * and {@see DefineRankCommand}.
 */
#[AsCommand(
    name: 'app:grant-administrator',
    description: 'Grant administrator (staff) status to a sign-in account, at a given rank.',
)]
final class GrantAdministratorCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('signInAccountId', InputArgument::REQUIRED, 'The Users-context sign-in account id')
            ->addArgument('rankId', InputArgument::REQUIRED, 'The rank id to grant the administrator');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $signInAccountId = $input->getArgument('signInAccountId');
        $rankId = $input->getArgument('rankId');

        if (!is_string($signInAccountId) || $signInAccountId === '') {
            $io->error('The signInAccountId argument must be a non-empty string.');

            return Command::INVALID;
        }

        if (!is_string($rankId) || $rankId === '') {
            $io->error('The rankId argument must be a non-empty string.');

            return Command::INVALID;
        }

        try {
            $envelope = $this->commandBus->dispatch(new GrantAdministrator(
                $signInAccountId,
                $rankId,
                ActorKind::System->value,
            ));
        } catch (HandlerFailedException $e) {
            return $this->reportHandlerFailure($e, $io);
        }

        return $this->reportResult($envelope, $io);
    }

    private function reportResult(Envelope $envelope, SymfonyStyle $io): int
    {
        $id = $envelope->last(HandledStamp::class)?->getResult();

        if (!$id instanceof AdministratorId) {
            $io->error(sprintf(
                'GrantAdministrator handler did not return an AdministratorId (got %s).',
                get_debug_type($id),
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf('Granted administrator status (id %s).', $id->value));

        return Command::SUCCESS;
    }

    /**
     * Symfony Messenger wraps every handler exception in
     * HandlerFailedException; the actual domain exception is the chained
     * previous. Walk the chain so a SignInAccountAlreadyAnAdministrator /
     * RankNotFound / RankIsRetired surfaces as a clean CLI error rather
     * than a wrapped stack trace.
     */
    private function reportHandlerFailure(HandlerFailedException $e, SymfonyStyle $io): int
    {
        $cause = $e->getPrevious();

        while ($cause !== null) {
            if ($cause instanceof AdministrationDomainException) {
                $io->error($cause->getMessage());

                return Command::INVALID;
            }

            $cause = $cause->getPrevious();
        }

        $io->error('Failed to grant administrator status for an unknown reason.');

        return Command::FAILURE;
    }
}
