<?php

declare(strict_types=1);

namespace App\Tests\Integration\Administration\Security;

use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Command\ContainerDebugCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Pins the production `administration.privilege_grant_source` tagged
 * iterator to exactly the two Doctrine-backed sources this slice ships,
 * via the same public `debug:container --tag=...` contract a developer
 * would use to check it by hand — not reflection into
 * {@see \App\Administration\Infrastructure\Security\UnionOfGrantSources}'s
 * private state, which this codebase's testing conventions forbid.
 *
 * The `_instanceof` rule in services.yaml auto-tags any
 * {@see \App\Administration\Domain\PrivilegeGrantSource} implementation,
 * with no distinction between a production adapter and a test double —
 * a class under src/ implementing the interface joins the production
 * union unless explicitly excluded from service discovery. This test
 * exists because that exclusion was missing for
 * {@see \App\Administration\Infrastructure\Persistence\InMemory\InMemoryPrivilegeGrantSource}
 * until this exact regression was caught in review: without it, the
 * fake source (which starts empty but is fully mutable via its own
 * `grant()` method) would have been a live participant in every
 * privilege resolution in production.
 */
#[Medium]
final class PrivilegeGrantSourceWiringTest extends KernelTestCase
{
    #[Test]
    #[TestDox('The production privilege grant source union contains exactly RankRoleGrants and DirectRoleGrants.')]
    public function union_contains_only_the_real_doctrine_sources(): void
    {
        $kernel = self::bootKernel();

        // FrameworkBundle registers this command under a fixed service
        // id, not its class name — see console.php in the bundle's own
        // DI config. It also calls getApplication()->getKernel()
        // internally, so it needs a real Application wrapping the
        // booted kernel, not a bare CommandTester.
        $command = static::getContainer()->get('console.command.container_debug');
        self::assertInstanceOf(ContainerDebugCommand::class, $command);
        $command->setApplication(new Application($kernel));

        $tester = new CommandTester($command);
        $tester->execute(['--tag' => 'administration.privilege_grant_source']);
        $output = $tester->getDisplay();

        self::assertStringContainsString(
            'App\Administration\Infrastructure\Persistence\Doctrine\Read\RankRoleGrants',
            $output,
        );
        self::assertStringContainsString(
            'App\Administration\Infrastructure\Persistence\Doctrine\Read\DirectRoleGrants',
            $output,
        );
        self::assertStringNotContainsString(
            'InMemoryPrivilegeGrantSource',
            $output,
        );
    }
}
