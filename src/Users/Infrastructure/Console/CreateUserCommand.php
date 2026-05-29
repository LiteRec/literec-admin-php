<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Console;

use App\Users\Application\Command\RegisterUser;
use App\Users\Domain\Exception\InvalidUsername;
use App\Users\Domain\Exception\UsernameAlreadyTaken;
use App\Users\Domain\ValueObject\UserId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use ValueError;

/**
 * Thin adapter that decodes CLI input into a {@see RegisterUser} command and
 * dispatches it via the command bus. Contains zero business logic.
 */
#[AsCommand(
    name: 'app:create-user',
    description: 'Create a staff user with a hashed password.',
)]
final class CreateUserCommand extends Command
{
    private const int MINIMUM_PASSWORD_LENGTH = 8;

    public function __construct(private readonly MessageBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The unique username')
            ->addArgument(
                'roles',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Extra roles to grant, space-separated (e.g. ROLE_ADMIN)',
                [],
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                'The plaintext password; if omitted, you are prompted for it without echo',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $credentials = $this->readCredentials($input, $io);
        if ($credentials === null) {
            return Command::INVALID;
        }

        try {
            $envelope = $this->commandBus->dispatch(new RegisterUser(
                $credentials['username'],
                $credentials['password'],
                $this->readRoles($input),
            ));
        } catch (HandlerFailedException $e) {
            return $this->reportHandlerFailure($e, $io);
        }

        return $this->reportResult($envelope, $credentials['username'], $io);
    }

    /**
     * Validates the CLI credentials, printing an error and returning null on
     * the first problem so the caller can exit INVALID once.
     *
     * @return array{username: string, password: string}|null
     */
    private function readCredentials(InputInterface $input, SymfonyStyle $io): ?array
    {
        $username = $input->getArgument('username');
        if (! is_string($username) || $username === '') {
            $io->error('The username argument must be a non-empty string.');

            return null;
        }

        $password = $this->readPassword($input, $io);
        if ($password === null) {
            return null;
        }

        return ['username' => $username, 'password' => $password];
    }

    /**
     * Reads the password from the --password option or an interactive hidden
     * prompt, enforcing the non-empty and minimum-length rules. Returns null
     * (after printing the error) when the input is unusable.
     */
    private function readPassword(InputInterface $input, SymfonyStyle $io): ?string
    {
        $password = $input->getOption('password') ?? $io->askHidden('Password');

        if (! is_string($password) || $password === '') {
            $io->error('A non-empty password is required (pass --password or answer the prompt).');

            return null;
        }

        if (mb_strlen($password, 'UTF-8') < self::MINIMUM_PASSWORD_LENGTH) {
            $io->error(sprintf('The password must be at least %d characters long.', self::MINIMUM_PASSWORD_LENGTH));

            return null;
        }

        return $password;
    }

    /**
     * @return list<string>
     */
    private function readRoles(InputInterface $input): array
    {
        $rolesArgument = $input->getArgument('roles');
        $roles = [];

        if (is_array($rolesArgument)) {
            foreach ($rolesArgument as $role) {
                if (is_string($role)) {
                    $roles[] = $role;
                }
            }
        }

        return $roles;
    }

    /**
     * Reports the dispatch outcome: SUCCESS when the handler returned a
     * UserId, FAILURE otherwise (no stamp or an unexpected result type).
     */
    private function reportResult(Envelope $envelope, string $username, SymfonyStyle $io): int
    {
        $id = $envelope->last(HandledStamp::class)?->getResult();

        if (! $id instanceof UserId) {
            $io->error(sprintf(
                'RegisterUser handler did not return a UserId (got %s).',
                get_debug_type($id),
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf('Created user "%s" (id %s).', $username, $id->value));

        return Command::SUCCESS;
    }

    /**
     * Symfony Messenger wraps every handler exception in HandlerFailedException;
     * the actual domain exception is the chained previous. Walk the chain so a
     * UsernameAlreadyTaken / InvalidUsername / ValueError surfaces as a clean
     * CLI error rather than a wrapped stack trace.
     */
    private function reportHandlerFailure(HandlerFailedException $e, SymfonyStyle $io): int
    {
        $cause = $e->getPrevious();

        // HandlerFailedException can wrap multiple handler errors; walk the
        // chain in case the outermost previous is itself a wrapper.
        while ($cause !== null) {
            if ($cause instanceof UsernameAlreadyTaken) {
                $io->error($cause->getMessage());

                return Command::FAILURE;
            }

            if ($cause instanceof InvalidUsername) {
                $io->error($cause->getMessage());

                return Command::INVALID;
            }

            if ($cause instanceof ValueError) {
                $io->error(sprintf('Unknown role: %s', $cause->getMessage()));

                return Command::INVALID;
            }

            $cause = $cause->getPrevious();
        }

        throw $e;
    }
}
