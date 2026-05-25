<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
#[CoversNothing]
final class CatalogNamespaceSmokeTest extends TestCase
{
    #[Test]
    #[TestDox(
        'Catalog directory tree exists with Domain, Application, Infrastructure, '
        . 'and Integration layers ready for upcoming sprint work.'
    )]
    public function catalog_directory_tree_exists(): void
    {
        $catalogRoot = \dirname(__DIR__, 3) . '/src/Catalog';

        self::assertDirectoryExists($catalogRoot . '/Domain');
        self::assertDirectoryExists($catalogRoot . '/Application');
        self::assertDirectoryExists($catalogRoot . '/Infrastructure');
        self::assertDirectoryExists($catalogRoot . '/Integration');
    }
}
