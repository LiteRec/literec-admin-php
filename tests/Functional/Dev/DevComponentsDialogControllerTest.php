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
 * Smoke-tests the HTMX dialog fragment backing the /_dev/components showcase
 * (LRA-185). Production isolation is enforced and verified the same way as
 * {@see DevComponentsControllerTest} — see that class's docblock.
 */
#[Large]
#[Group('database')]
final class DevComponentsDialogControllerTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'dev_components_dialog_e2e';

    #[Test]
    #[TestDox('GET /_dev/components/dialog renders the shared modal shell with the Organic dialog treatment.')]
    public function dialog_fragment_renders_the_modal_shell(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/_dev/components/dialog');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#dev-components-modal');
        self::assertSelectorExists('.rounded-modal.bg-litrec-surface-2.shadow-pop');
    }
}
