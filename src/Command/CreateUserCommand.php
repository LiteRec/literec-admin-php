<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Seeds a staff user with a hashed password — used to create test
 * accounts for the login flow.
 */
#[AsCommand(
    name: 'app:create-user',
    description: 'Create a staff user with a hashed password.',
)]
final class CreateUserCommand extends Command
{
    private const int MINIMUM_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The unique username')
            ->addArgument('password', InputArgument::REQUIRED, 'The plaintext password (it will be hashed)')
            ->addArgument(
                'roles',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Extra roles to grant, space-separated (e.g. ROLE_ADMIN)',
                [],
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');
        $password = $input->getArgument('password');

        if (!is_string($username) || $username === '' || !is_string($password) || $password === '') {
            $io->error('The username and password arguments must be non-empty strings.');

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

        if ($this->userRepository->findOneBy(['username' => $username]) !== null) {
            $io->error(sprintf('A user with username "%s" already exists.', $username));

            return Command::FAILURE;
        }

        $user = new User($username);
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Created user "%s".', $username));

        return Command::SUCCESS;
    }
}
