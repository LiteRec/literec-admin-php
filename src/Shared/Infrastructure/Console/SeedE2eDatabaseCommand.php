<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Shared\Infrastructure\Database\E2eDatabaseGuard;
use App\Shared\Infrastructure\Database\PostgresSnapshot;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Seeds the persistent E2E database and captures a restore snapshot (LRA-177).
 *
 * Forward-migrates the existing database (no drop), then — when the database is
 * empty or --fresh is given — loads the `test` fixtures (--fresh is duplicate-safe
 * because the loader purges first) and captures a template snapshot so
 * {@see ResetE2eDatabaseCommand} can restore the known-good state in seconds.
 *
 * When the database is already seeded and --fresh is not given, the command is a
 * no-op: it loads nothing and, crucially, does NOT re-capture the snapshot, so a
 * baseline can never be overwritten by a state a prior test run left dirty. Safe
 * to run repeatedly. Refuses to touch anything but the E2E lane.
 */
#[AsCommand(
    name: 'app:seed:e2e',
    description: 'Seed the persistent E2E database and capture a restore snapshot.',
)]
final class SeedE2eDatabaseCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PostgresSnapshot $snapshot,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'fresh',
            null,
            InputOption::VALUE_NONE,
            'Purge and reload the fixtures even if the database is already seeded.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Loading fixtures dispatches thousands of commands; under the debug
        // SQL collector this exceeds the default memory cap. Lift it for this
        // one-shot maintenance run.
        ini_set('memory_limit', '-1');

        $database = $this->connection->getDatabase() ?? '';
        if (! E2eDatabaseGuard::isE2eDatabase($database)) {
            $io->error(sprintf(
                'Refusing to seed "%s": not an E2E database (the name must contain "e2e"). '
                . 'Point DATABASE_URL at the E2E lane (app_e2e).',
                $database,
            ));

            return Command::FAILURE;
        }

        $created = $this->runSubCommand('doctrine:database:create', ['--if-not-exists' => true], $output);
        if ($created !== Command::SUCCESS) {
            $io->error('Could not ensure the E2E database exists.');

            return Command::FAILURE;
        }

        $migrated = $this->runSubCommand('doctrine:migrations:migrate', ['--allow-no-migration' => true], $output);
        if ($migrated !== Command::SUCCESS) {
            $io->error('Forward migration failed.');

            return Command::FAILURE;
        }

        // When the database is already seeded and no rebuild is requested, leave
        // it AND its snapshot untouched: re-capturing here could overwrite the
        // known-good baseline with a state a prior test run left dirty.
        if ($input->getOption('fresh') !== true && $this->alreadySeeded()) {
            $io->note(sprintf(
                'Database "%s" is already seeded; left it and its snapshot untouched '
                . '(use --fresh to rebuild and re-snapshot).',
                $database,
            ));

            return Command::SUCCESS;
        }

        $loaded = $this->runSubCommand('doctrine:fixtures:load', ['--group' => ['test']], $output);
        if ($loaded !== Command::SUCCESS) {
            $io->error('Fixture load failed.');

            return Command::FAILURE;
        }

        try {
            $this->snapshot->capture();
        } catch (Throwable $e) {
            $io->error(sprintf('Snapshot capture failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'E2E database "%s" seeded; snapshot "%s" captured.',
            $database,
            $this->snapshot->snapshotName(),
        ));

        return Command::SUCCESS;
    }

    /**
     * The seed always registers the curated `admin` staff user, so a non-empty
     * user table is a reliable "already seeded" sentinel.
     */
    private function alreadySeeded(): bool
    {
        try {
            $count = $this->connection->fetchOne('SELECT COUNT(*) FROM "user"');
        } catch (DbalException) {
            // No user table yet (e.g. an unmigrated database): treat as not
            // seeded so the fixture load runs.
            return false;
        }

        return is_numeric($count) && (int) $count > 0;
    }

    /**
     * @param array<string, scalar|array<int, string>> $arguments
     */
    private function runSubCommand(string $name, array $arguments, OutputInterface $output): int
    {
        $command = $this->getApplication()?->find($name);
        if ($command === null) {
            return Command::FAILURE;
        }

        $sub = new ArrayInput(array_merge(['command' => $name], $arguments));
        $sub->setInteractive(false);

        return $command->run($sub, $output);
    }
}
