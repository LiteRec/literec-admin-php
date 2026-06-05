<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Database;

use App\Shared\Infrastructure\Database\E2eDatabaseGuard;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class E2eDatabaseGuardTest extends TestCase
{
    /**
     * @return Generator<string, array{string, bool}>
     */
    public static function databaseNames(): Generator
    {
        yield 'e2e lane (physical name)'      => ['app_e2e_test', true];
        yield 'e2e base name'                 => ['app_e2e', true];
        yield 'uppercase token'              => ['APP_E2E_TEST', true];
        yield 'functional/integration lane'   => ['app_test', false];
        yield 'dev / prod database'           => ['app', false];
        yield 'empty name'                    => ['', false];
        yield 'lookalike without token'       => ['app_e_test', false];
    }

    #[Test]
    #[DataProvider('databaseNames')]
    #[TestDox('Recognises only databases whose name contains "e2e": $_dataName.')]
    public function recognises_only_the_e2e_lane(string $databaseName, bool $expected): void
    {
        self::assertSame($expected, E2eDatabaseGuard::isE2eDatabase($databaseName));
    }
}
