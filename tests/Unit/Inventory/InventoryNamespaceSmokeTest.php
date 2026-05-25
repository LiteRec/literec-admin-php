<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
#[CoversNothing]
final class InventoryNamespaceSmokeTest extends TestCase
{
    #[Test]
    #[TestDox(
        'Inventory directory tree exists with Domain, Application, Infrastructure, '
        . 'and Integration layers ready for upcoming sprint work.'
    )]
    public function inventory_directory_tree_exists(): void
    {
        $inventoryRoot = \dirname(__DIR__, 3) . '/src/Inventory';

        self::assertDirectoryExists($inventoryRoot . '/Domain');
        self::assertDirectoryExists($inventoryRoot . '/Application');
        self::assertDirectoryExists($inventoryRoot . '/Infrastructure');
        self::assertDirectoryExists($inventoryRoot . '/Integration');
    }
}
