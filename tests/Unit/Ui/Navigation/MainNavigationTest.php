<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ui\Navigation;

use App\Ui\Navigation\MainNavigation;
use App\Ui\Navigation\NavigationItem;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Locks the staff-admin main nav structure so the seven top-level categories
 * (and their legacy sub-items) cannot drift silently. The labels and order
 * are the user-facing contract — touching this test means accepting a
 * visible UI change.
 */
#[Small]
final class MainNavigationTest extends TestCase
{
    #[Test]
    #[TestDox('Builds the seven top-level staff-admin categories in legacy order.')]
    public function it_exposes_seven_top_level_items_in_the_expected_order(): void
    {
        $nav = MainNavigation::build();

        $labels = array_map(static fn (NavigationItem $i): string => $i->label, $nav->items);

        self::assertSame(
            [
                'Cash Register',
                'Programs',
                'Users',
                'Memberships',
                'Facilities',
                'Reports',
                'Communications',
            ],
            $labels,
        );
    }

    #[Test]
    #[TestDox('Every top-level category exposes its documented sub-items.')]
    public function each_category_exposes_its_documented_sub_items(): void
    {
        $expected = [
            'Cash Register' => [
                'Inventory',
                'POS Transactions',
                'All Payment Plan Invoices',
                'All EFT Invoices',
                'Gift Cards',
            ],
            'Programs' => [
                'Program Types',
                'Program Payment Plan Invoices',
                'Program EFT Invoices',
            ],
            'Users' => [
                'Refunds',
                'User Groups',
            ],
            'Memberships' => [
                'Membership Types',
                'Membership Logger',
                'Membership Cards',
                'Membership Payment Plan Invoices',
                'Membership EFT Invoices',
            ],
            'Facilities' => [
                'Calendar',
                'Rental Codes',
                'Manage User Rentals',
                'Rental Payment Plan Invoices',
                'Rental EFT Invoices',
            ],
            'Reports' => [
                'General Ledger Reports',
                'User Reports',
                'Program Reports',
                'Membership Reports',
                'Facility Reports',
                'Inventory Reports',
                'Payment Method Reports',
            ],
            'Communications' => [
                'Send Message',
                'Group Builder',
                'Twitter',
                'Communication Settings',
            ],
        ];

        $actual = [];
        foreach (MainNavigation::build()->items as $item) {
            $actual[$item->label] = array_map(static fn (NavigationItem $c): string => $c->label, $item->children);
        }

        self::assertSame($expected, $actual);
    }

    #[Test]
    #[TestDox('Every route name across the structure is unique.')]
    public function every_route_name_is_unique(): void
    {
        $routes = [];
        foreach (MainNavigation::build()->items as $item) {
            $routes[] = $item->route;
            foreach ($item->children as $child) {
                $routes[] = $child->route;
            }
        }

        self::assertSame($routes, array_unique($routes));
    }
}
