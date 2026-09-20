<?php

declare(strict_types=1);

namespace App\Tests\Functional\Administration;

use App\Administration\Application\Command\DefineRank;
use App\Administration\Application\Command\GrantAdministrator;
use App\Administration\Application\Command\RevokeAdministrator;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorStanding;
use App\Administration\Domain\ValueObject\RankId;
use App\Shared\Infrastructure\Fixtures\HandledResult;
use App\Tests\Support\EventListener\StampsCurrentAdministratorStandingHeader;
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
 * same {@see \App\Administration\Application\Security\CurrentAdministrator}
 * service instance — alive across both
 * `$client->request()` calls below, simulating the one scenario where a
 * naive per-instance memo would leak a stale standing: FrankenPHP worker
 * mode, where a service instance can survive many requests inside one
 * long-lived process. A normal test-client reboot between requests would
 * trivially pass even with the memoisation bug this test exists to catch,
 * since a fresh instance would replace the memoising one on every call
 * regardless of whether reset() is wired correctly.
 *
 * The assertions read {@see StampsCurrentAdministratorStandingHeader}'s
 * response header rather than re-querying `CurrentAdministrator` from
 * the container after each `$client->request()` call returns. That
 * distinction matters: `kernel.terminate` — and therefore
 * {@see \App\Administration\Infrastructure\Security\SecurityCurrentAdministrator}'s
 * reset() — fires at the END of request handling, so a read taken after
 * `request()` returns is already past that request's own reset and
 * would recompute fresh regardless of whether reset() actually works,
 * proving nothing about what the request's own handling observed. The
 * header is stamped from inside the request, via `kernel.response`
 * (which fires before `kernel.terminate`), so it reflects exactly what
 * application code resolving standing() during that request would have
 * seen — stale "Active" for the second request if reset() were broken,
 * since nothing would have cleared the memo the first request set
 * before this second request began handling.
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
        self::assertSame(
            AdministratorStanding::Active->value,
            $client->getResponse()->headers->get(StampsCurrentAdministratorStandingHeader::HEADER),
        );

        $bus->dispatch(new RevokeAdministrator(
            $administratorId->value,
            'Left the organization.',
            ActorKind::System->value,
        ));

        $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSame(
            AdministratorStanding::Revoked->value,
            $client->getResponse()->headers->get(StampsCurrentAdministratorStandingHeader::HEADER),
        );
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
