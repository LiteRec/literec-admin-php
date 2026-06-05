<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Database;

use App\Shared\Infrastructure\Database\PostgresSnapshot;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class PostgresSnapshotTest extends TestCase
{
    #[Test]
    #[TestWith(['app_e2e_test', 'app_e2e_test_snapshot'])]
    #[TestWith(['app_e2e', 'app_e2e_snapshot'])]
    #[TestDox('Derives the snapshot database name by appending the _snapshot suffix.')]
    public function derives_the_snapshot_name(string $database, string $expected): void
    {
        self::assertSame($expected, PostgresSnapshot::snapshotNameFor($database));
    }
}
