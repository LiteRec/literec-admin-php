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
 * Smoke-tests the dev/test-only Organic component showcase (LRA-185).
 *
 * Production isolation is enforced by the `#[When(env: 'dev')]` and
 * `#[When(env: 'test')]` attributes on {@see \App\Controller\DevComponentsController},
 * the same mechanism used by {@see MemberLookupDemoControllerTest} — see that
 * test's docblock for why the prod-404 path is not exercised here too.
 */
#[Large]
#[Group('database')]
final class DevComponentsControllerTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'dev_components_e2e';

    #[Test]
    #[TestDox('GET /_dev/components renders every restyled lr-* component with its is-active/aria-current hooks.')]
    public function components_page_renders_every_component(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/_dev/components');

        self::assertResponseIsSuccessful();

        // Buttons.
        self::assertSelectorExists('.lr-btn.lr-btn-primary');
        self::assertSelectorExists('.lr-btn.lr-btn-icon');

        // Surfaces.
        self::assertSelectorExists('.lr-card-context');

        // Tags, including the outline modifier.
        self::assertSelectorExists('.lr-badge.success');
        self::assertSelectorExists('.lr-badge.outline');

        // Inputs.
        self::assertSelectorExists('.lr-input');
        self::assertSelectorExists('.lr-select');
        self::assertSelectorExists('.litrec-input');

        // Segmented control and tabs keep the selectors functional tests rely on.
        self::assertSelectorExists('.lr-seg a[aria-current="page"]');
        self::assertSelectorExists('.lr-tabs .lr-tab.is-active');

        // Table, dialog trigger, and the remaining small components.
        self::assertSelectorExists('.lr-table');
        self::assertSelectorExists('[data-testid="open-components-dialog"]');
        self::assertSelectorExists('.lr-chip');
        self::assertSelectorExists('.lr-stepper');
        self::assertSelectorExists('.lr-kbd');
        self::assertSelectorExists('.lr-iconbtn');
        self::assertSelectorExists('.lr-avatar');
    }
}
