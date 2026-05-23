<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Large]
#[Group('smoke')]
final class HomeControllerTest extends WebTestCase
{
    #[Test]
    #[TestDox('Anonymous users are redirected from / to /login.')]
    public function home_page_redirects_anonymous_users_to_login(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    #[Test]
    #[TestDox('/health returns 200 with a JSON {"status":"ok"} body.')]
    public function health_endpoint_returns_ok_status(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok"}',
            (string) $client->getResponse()->getContent(),
        );
    }
}
