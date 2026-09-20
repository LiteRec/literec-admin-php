<?php

declare(strict_types=1);

namespace App\Tests\Functional\Administration;

use App\Administration\Application\Command\DefineRank;
use App\Administration\Application\Command\GrantAdministrator;
use App\Administration\Application\Command\RevokeAdministrator;
use App\Administration\Application\Security\CurrentAdministrator;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorStanding;
use App\Administration\Domain\ValueObject\RankId;
use App\Shared\Infrastructure\Fixtures\HandledResult;
use App\Users\Application\Command\RegisterUser;
use App\Users\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The acceptance criterion LRA-279 exists to prove: revoking an
 * administrator takes effect starting with the very next request, not
 * retroactively within the request still in flight and not "eventually"
 * on some later request either.
 *
 * `$client->disableReboot()` keeps the same kernel — and therefore the
 * same {@see CurrentAdministrator} service instance — alive across both
 * `$client->request()` calls below, simulating the one scenario where a
 * naive per-instance memo would leak a stale standing: FrankenPHP worker
 * mode, where a service instance can survive many requests inside one
 * long-lived process. A normal test-client reboot between requests would
 * trivially pass even with the memoisation bug this test exists to catch,
 * since a fresh instance would replace the memoising one on every call
 * regardless of whether reset() is wired correctly.
 *
 * `kernel.terminate` — and therefore {@see \App\Administration\Infrastructure\Security\SecurityCurrentAdministrator}'s
 * reset(), via its ResetInterface/kernel.reset autoconfiguration — fires
 * automatically at the end of each `$client->request()` call, including
 * both calls below. So the sequence is: first request resolves (and
 * memoises) "Active" — revoke happens in test code, outside any request
 * — second request's kernel.terminate has already cleared that memo by
 * the time this test reads standing() again, so it must recompute fresh
 * and see "Revoked". Without a working reset(), the stale "Active" memo
 * set in test code between the two requests would survive the second
 * request's terminate untouched, and this test would fail.
 */
#[Large]
#[Group('database')]
final class RevocationTakesEffectNextRequestTest extends WebTestCase
{
    private const string USERNAME = 'admin_revocation_e2e';
    private const string PASSWORD = 'CorrectHorseBattery!'; // NOSONAR
    private const string RANK_NAME = 'Revocation E2E Rank';

    #[Test]
    #[TestDox('Revoking an administrator is reflected starting with the next request, not the one still in flight.')]
    public function revocation_takes_effect_on_the_next_request(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = static::getContainer();
        $bus = $container->get(MessageBusInterface::class);

        $userId = HandledResult::from(
            $bus->dispatch(new RegisterUser(self::USERNAME, self::PASSWORD)),
            UserId::class,
        );
        $rankId = HandledResult::from(
            $bus->dispatch(new DefineRank(self::RANK_NAME, 50, ActorKind::System->value)),
            RankId::class,
        );
        $administratorId = HandledResult::from(
            $bus->dispatch(new GrantAdministrator($userId->value, $rankId->value, ActorKind::System->value)),
            AdministratorId::class,
        );

        $this->logIn($client);

        $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        $currentAdministrator = $container->get(CurrentAdministrator::class);
        $beforeRevocation = $currentAdministrator->standing();
        self::assertNotNull($beforeRevocation);
        self::assertSame($administratorId->value, $beforeRevocation->administratorId);
        self::assertSame(AdministratorStanding::Active->value, $beforeRevocation->standing);

        $bus->dispatch(new RevokeAdministrator(
            $administratorId->value,
            'Left the organization.',
            ActorKind::System->value,
        ));

        $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        $afterRevocation = $container->get(CurrentAdministrator::class)->standing();
        self::assertNotNull($afterRevocation);
        self::assertSame(AdministratorStanding::Revoked->value, $afterRevocation->standing);
    }

    private function logIn(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => self::USERNAME,
            '_password' => self::PASSWORD,
        ]);
        $client->submit($form);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }
}
