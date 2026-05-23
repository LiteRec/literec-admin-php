<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ui\Navigation;

use App\Ui\Navigation\MainNavigation;
use App\Ui\Navigation\NavigationItem;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * Guarantees that every route name referenced by the main navigation is
 * registered with the Symfony router. Without this guard a typo in
 * MainNavigation would produce a runtime exception inside the Twig
 * `path()` call rather than failing at PR time.
 */
#[Medium]
final class NavigationRoutesTest extends KernelTestCase
{
    #[Test]
    #[TestDox('Every route name referenced by the main navigation is registered.')]
    public function every_referenced_route_exists(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $collection = $router->getRouteCollection();

        foreach (MainNavigation::build()->items as $item) {
            $this->assertRouteRegistered($item, $collection);
            foreach ($item->children as $child) {
                $this->assertRouteRegistered($child, $collection);
            }
        }
    }

    private function assertRouteRegistered(
        NavigationItem $item,
        RouteCollection $collection,
    ): void {
        self::assertNotNull(
            $collection->get($item->route),
            sprintf('Route "%s" referenced by nav item "%s" is not registered.', $item->route, $item->label),
        );
    }
}
