<?php

declare(strict_types=1);

namespace App\Tests\Functional\Dev;

use App\Tests\Support\Trait\SignsInUsers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke-tests the dev/test-only Member Lookup demo page (LRA-46).
 *
 * Production isolation is enforced by the `#[When(env: 'dev')]` and
 * `#[When(env: 'test')]` attributes on {@see \App\Controller\MemberLookupDemoController}:
 * Symfony's DI container only registers the class as a service in those two
 * environments, which means the route loader never sees the `#[Route]`
 * attribute in prod. Verifying the prod 404 path inside a single PHPUnit
 * process would require booting a second kernel under `APP_ENV=prod` (the
 * KernelTestCase API boots exactly one), so that case is intentionally
 * deferred and documented in the ticket completion report rather than
 * fabricated here.
 */
#[Large]
#[Group('database')]
final class MemberLookupDemoControllerTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'member_lookup_demo_e2e';

    #[Test]
    #[TestDox('GET /_dev/member-lookup-demo renders the demo shell + the lookup dialog markup.')]
    public function demo_page_renders_in_test_env(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/_dev/member-lookup-demo');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="open-member-lookup"]');
        self::assertSelectorExists('[data-testid="last-selected"]');

        // The dialog template ships an inline Alpine host with the
        // openMemberLookup hook; assert it is included so a future
        // refactor that drops the embed is caught here.
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('member-lookup-host', $body);
        self::assertStringContainsString('openMemberLookup', $body);
    }
}
