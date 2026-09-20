<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Console;

use App\Administration\Application\Command\DefineRank;
use App\Administration\Domain\Exception\AdministrationDomainException;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\RankId;
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
 * Thin adapter that decodes CLI input into a {@see DefineRank} command and
 * dispatches it via the command bus. Contains zero business logic. Used to
 * bootstrap an installation's rank ladder — see {@see \App\Administration\Infrastructure\Fixtures\RankLadderFixtures}
 * for the default ladder seeded through the same command.
 */
#[AsCommand(
    name: 'app:define-rank',
    description: 'Define an administration rank with a name and a seniority level.',
)]
final class DefineRankCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The rank name')
            ->addArgument(
                'seniority',
                InputArgument::REQUIRED,
                'The seniority level (0-100; ascending is less senior)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $seniority = $input->getArgument('seniority');

        if (!is_string($name) || $name === '') {
            $io->error('The name argument must be a non-empty string.');

            return Command::INVALID;
        }

        if (!is_string($seniority) || !ctype_digit($seniority)) {
            $io->error('The seniority argument must be a non-negative integer.');

            return Command::INVALID;
        }

        try {
            $envelope = $this->commandBus->dispatch(new DefineRank(
                $name,
                (int) $seniority,
                ActorKind::System->value,
            ));
        } catch (HandlerFailedException $e) {
            return $this->reportHandlerFailure($e, $io);
        }

        return $this->reportResult($envelope, $name, $io);
    }

    private function reportResult(Envelope $envelope, string $name, SymfonyStyle $io): int
    {
        $id = $envelope->last(HandledStamp::class)?->getResult();

        if (!$id instanceof RankId) {
            $io->error(sprintf('DefineRank handler did not return a RankId (got %s).', get_debug_type($id)));

            return Command::FAILURE;
        }

        $io->success(sprintf('Defined rank "%s" (id %s).', $name, $id->value));

        return Command::SUCCESS;
    }

    /**
     * Symfony Messenger wraps every handler exception in
     * HandlerFailedException; the actual domain exception is the chained
     * previous. Walk the chain so a DuplicateRankName / InvalidRankName /
     * InvalidSeniorityLevel surfaces as a clean CLI error rather than a
     * wrapped stack trace.
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

        $io->error('Failed to define the rank for an unknown reason.');

        return Command::FAILURE;
    }
}
