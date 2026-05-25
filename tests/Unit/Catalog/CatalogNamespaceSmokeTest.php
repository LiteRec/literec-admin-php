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
    #[TestDox('App\\Catalog directory tree exists with Domain, Application, Infrastructure, and Integration layers and the namespace is mapped by the composer autoloader.')]
    public function catalog_namespace_is_registered_and_layered(): void
    {
        $catalogRoot = \dirname(__DIR__, 3) . '/src/Catalog';

        self::assertDirectoryExists($catalogRoot . '/Domain');
        self::assertDirectoryExists($catalogRoot . '/Application');
        self::assertDirectoryExists($catalogRoot . '/Infrastructure');
        self::assertDirectoryExists($catalogRoot . '/Integration');

        // The App\ -> src/ PSR-4 mapping from composer.json must resolve
        // anything under App\Catalog\ to src/Catalog/, so future classes
        // dropped into Domain/, Application/, Infrastructure/, or
        // Integration/ are reachable without further autoload changes.
        $loader = require \dirname(__DIR__, 3) . '/vendor/autoload.php';
        self::assertArrayHasKey('App\\', $loader->getPrefixesPsr4());
    }
}
