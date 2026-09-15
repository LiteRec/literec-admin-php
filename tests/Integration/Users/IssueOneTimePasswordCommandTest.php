<?php

declare(strict_types=1);

namespace App\Tests\Integration\Users;

use App\Users\Application\Command\RegisterUser;
use App\Users\Infrastructure\Console\IssueOneTimePasswordCommand;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Integration coverage for the `app:issue-one-time-password` console
 * command (LRA-213 Part A's staff entry point).
 */
#[Medium]
#[Group('database')]
final class IssueOneTimePasswordCommandTest extends KernelTestCase
{
    #[Test]
    #[TestDox('Prints a 12-character credential and exits 0 for a known user.')]
    public function prints_a_credential_and_exits_success_for_a_known_user(): void
    {
        self::bootKernel();
        $this->seedUser('otp_console_e2e');

        $tester = new CommandTester($this->command());
        $exitCode = $tester->execute(['username' => 'otp_console_e2e']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertMatchesRegularExpression(
            '/One-time password for "otp_console_e2e": [^\s]{12}/',
            $tester->getDisplay(),
        );
    }

    #[Test]
    #[TestDox('Exits non-zero for an unknown username.')]
    public function exits_non_zero_for_an_unknown_username(): void
    {
        self::bootKernel();

        $tester = new CommandTester($this->command());
        $exitCode = $tester->execute(['username' => 'no_such_user_e2e']);

        self::assertNotSame(Command::SUCCESS, $exitCode);
    }

    private function seedUser(string $username): void
    {
        $bus = static::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $bus->dispatch(new RegisterUser($username, 'CorrectHorseBattery!')); // NOSONAR test fixture
    }

    private function command(): IssueOneTimePasswordCommand
    {
        $command = static::getContainer()->get(IssueOneTimePasswordCommand::class);
        self::assertInstanceOf(IssueOneTimePasswordCommand::class, $command);

        return $command;
    }
}
