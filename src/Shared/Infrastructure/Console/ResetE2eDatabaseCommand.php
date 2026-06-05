<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Shared\Infrastructure\Database\E2eDatabaseGuard;
use App\Shared\Infrastructure\Database\PostgresSnapshot;
use Doctrine\DBAL\Connection;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Restores the persistent E2E database from its snapshot in seconds (LRA-177).
 *
 * Drops the E2E database and recreates it from the template snapshot captured
 * by {@see SeedE2eDatabaseCommand} — no fixture commands are re-dispatched.
 * Refuses to touch anything but the E2E lane.
 */
#[AsCommand(
    name: 'app:reset:e2e',
    description: 'Restore the persistent E2E database from its snapshot in seconds.',
)]
final class ResetE2eDatabaseCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PostgresSnapshot $snapshot,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $database = $this->connection->getDatabase() ?? '';
        if (! E2eDatabaseGuard::isE2eDatabase($database)) {
            $io->error(sprintf(
                'Refusing to reset "%s": not an E2E database (the name must contain "e2e"). '
                . 'Point DATABASE_URL at the E2E lane (app_e2e).',
                $database,
            ));

            return Command::FAILURE;
        }

        try {
            $this->snapshot->restore();
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'E2E database "%s" restored from snapshot "%s".',
            $database,
            $this->snapshot->snapshotName(),
        ));

        return Command::SUCCESS;
    }
}
