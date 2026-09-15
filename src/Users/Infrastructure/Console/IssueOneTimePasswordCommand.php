<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Console;

use App\Users\Application\Command\IssueOneTimePassword;
use App\Users\Domain\Exception\InvalidUsername;
use App\Users\Domain\Exception\OneTimePasswordNotAllowed;
use App\Users\Domain\Exception\UserNotFound;
use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\OneTimePassword;
use App\Users\Domain\ValueObject\Username;
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
 * Staff entry point for issuing a one-time login credential (LRA-213 Part A).
 * The staff-facing admin dialog (Part B) reuses the same
 * {@see IssueOneTimePassword} command through the HTTP adapter once it ships;
 * until then this is the only way to issue one.
 */
#[AsCommand(
    name: 'app:issue-one-time-password',
    description: 'Issue a one-time login credential for a user, printed once.',
)]
final class IssueOneTimePasswordCommand extends Command
{
    public function __construct(
        private readonly Users $users,
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'The username to issue a one-time password for');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');
        if (!is_string($username) || $username === '') {
            $io->error('The username argument must be a non-empty string.');

            return Command::INVALID;
        }

        try {
            $user = $this->users->byUsername(Username::of($username));
        } catch (UserNotFound | InvalidUsername $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        try {
            $envelope = $this->commandBus->dispatch(new IssueOneTimePassword($user->id()->value));
        } catch (HandlerFailedException $e) {
            return $this->reportHandlerFailure($e, $io);
        }

        return $this->reportResult($envelope, $username, $io);
    }

    private function reportResult(Envelope $envelope, string $username, SymfonyStyle $io): int
    {
        $otp = $envelope->last(HandledStamp::class)?->getResult();

        if (!$otp instanceof OneTimePassword) {
            $io->error(sprintf(
                'IssueOneTimePassword handler did not return a OneTimePassword (got %s).',
                get_debug_type($otp),
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf('One-time password for "%s": %s', $username, $otp->value));
        $io->note('This credential is shown once and is not stored anywhere in plaintext.');

        return Command::SUCCESS;
    }

    private function reportHandlerFailure(HandlerFailedException $e, SymfonyStyle $io): int
    {
        $cause = $e->getPrevious();

        while ($cause !== null) {
            if ($cause instanceof OneTimePasswordNotAllowed) {
                $io->error($cause->getMessage());

                return Command::FAILURE;
            }

            $cause = $cause->getPrevious();
        }

        throw $e;
    }
}
