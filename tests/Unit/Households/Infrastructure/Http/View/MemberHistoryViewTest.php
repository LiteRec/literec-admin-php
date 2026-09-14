<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Infrastructure\Http\View;

use App\Households\Infrastructure\Http\View\MemberHistoryView;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Pins the tab/panel contract the History card (LRA-206) relies on: a
 * stable label per kind, which kind is the one real (non-placeholder)
 * view, and a slug shape that is safe to drop directly into a route
 * path segment and a DOM id without further escaping.
 */
#[Small]
final class MemberHistoryViewTest extends TestCase
{
    /**
     * @return Generator<string, array{MemberHistoryView, string, string}>
     */
    public static function viewCases(): Generator
    {
        yield 'Transactions'      => [MemberHistoryView::Transactions, 'transactions', 'Transactions'];
        yield 'Activities'        => [MemberHistoryView::Activities, 'activities', 'Activities'];
        yield 'Memberships'       => [MemberHistoryView::Memberships, 'memberships', 'Memberships'];
        yield 'Facility Rentals'  => [MemberHistoryView::FacilityRentals, 'facility-rentals', 'Facility Rentals'];
        yield 'Equipment Rentals' => [MemberHistoryView::EquipmentRentals, 'equipment-rentals', 'Equipment Rentals'];
        yield 'POS Purchases'     => [MemberHistoryView::PosPurchases, 'pos-purchases', 'POS Purchases'];
    }

    #[Test]
    #[DataProvider('viewCases')]
    #[TestDox('Backs each case with its URL slug and exposes the matching human label: $_dataName.')]
    public function exposes_value_and_label(MemberHistoryView $view, string $expectedValue, string $expectedLabel): void
    {
        self::assertSame($expectedValue, $view->value);
        self::assertSame($expectedLabel, $view->label());
    }

    /**
     * @return Generator<string, array{MemberHistoryView, bool}>
     */
    public static function comingSoonCases(): Generator
    {
        yield 'Transactions is the only real view' => [MemberHistoryView::Transactions, false];
        yield 'Activities is coming soon'           => [MemberHistoryView::Activities, true];
        yield 'Memberships is coming soon'           => [MemberHistoryView::Memberships, true];
        yield 'Facility Rentals is coming soon'      => [MemberHistoryView::FacilityRentals, true];
        yield 'Equipment Rentals is coming soon'     => [MemberHistoryView::EquipmentRentals, true];
        yield 'POS Purchases is coming soon'         => [MemberHistoryView::PosPurchases, true];
    }

    #[Test]
    #[DataProvider('comingSoonCases')]
    #[TestDox('Marks every view except Transactions as coming soon: $_dataName.')]
    public function marks_every_view_except_transactions_as_coming_soon(MemberHistoryView $view, bool $expected): void
    {
        self::assertSame($expected, $view->isComingSoon());
    }

    /**
     * @return Generator<string, array{MemberHistoryView}>
     */
    public static function everyView(): Generator
    {
        foreach (MemberHistoryView::cases() as $view) {
            yield $view->name => [$view];
        }
    }

    #[Test]
    #[DataProvider('everyView')]
    #[TestDox('Exposes a URL-safe kebab-case slug, since the route path and DOM ids embed it directly: $_dataName.')]
    public function exposes_url_safe_kebab_case_slugs(MemberHistoryView $view): void
    {
        self::assertMatchesRegularExpression('/^[a-z]+(-[a-z]+)*$/', $view->value);
    }
}
