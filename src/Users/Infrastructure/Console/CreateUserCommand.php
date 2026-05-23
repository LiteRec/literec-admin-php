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

        $username = $input->getArgument('username');

        if (!is_string($username) || $username === '') {
            $io->error('The username argument must be a non-empty string.');

            return Command::INVALID;
        }

        $password = $input->getOption('password');

        if ($password === null) {
            $password = $io->askHidden('Password');
        }

        if (!is_string($password) || $password === '') {
            $io->error('A non-empty password is required (pass --password or answer the prompt).');

            return Command::INVALID;
        }

        if (mb_strlen($password, 'UTF-8') < self::MINIMUM_PASSWORD_LENGTH) {
            $io->error(sprintf('The password must be at least %d characters long.', self::MINIMUM_PASSWORD_LENGTH));

            return Command::INVALID;
        }

        $rolesArgument = $input->getArgument('roles');
        $roles = [];

        if (is_array($rolesArgument)) {
            foreach ($rolesArgument as $role) {
                if (is_string($role)) {
                    $roles[] = $role;
                }
            }
        }

        try {
            $envelope = $this->commandBus->dispatch(new RegisterUser($username, $password, $roles));
        } catch (UsernameAlreadyTaken $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (InvalidUsername $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        } catch (ValueError $e) {
            // Role::from() threw on an unknown role string.
            $io->error(sprintf('Unknown role: %s', $e->getMessage()));

            return Command::INVALID;
        }

        $stamp = $envelope->last(HandledStamp::class);
        $id = $stamp?->getResult();

        $io->success(sprintf(
            'Created user "%s" (id %s).',
            $username,
            $id instanceof UserId ? $id->value : '?',
        ));

        return Command::SUCCESS;
    }
}
