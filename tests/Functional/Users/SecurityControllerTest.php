<?php

declare(strict_types=1);

namespace App\Tests\Functional\Users;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Large]
#[Group('smoke')]
final class SecurityControllerTest extends WebTestCase
{
    #[Test]
    #[TestDox('GET /login renders the LiteRecAdmin login form with username, password, and CSRF inputs.')]
    public function login_page_renders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'LiteRecAdmin');
        self::assertSelectorExists('form[action$="/login"]');
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
        self::assertSelectorExists('input[name="_csrf_token"]');
        self::assertSelectorExists('button[type="submit"]');
    }
}
