<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\View;

/**
 * The kinds of history shown in the member detail page's History card
 * (LRA-206). Each case backs one tab / tabpanel pair: the backing value
 * doubles as the route slug (`/history/{view}`) and the DOM id suffix
 * (`history-tab-{value}`, `history-view-{value}`), so it must stay a
 * URL-safe kebab-case token — see
 * {@see MemberHistoryViewTest::exposes_url_safe_kebab_case_slugs()}.
 *
 * {@see self::Transactions} is the only kind with a real (currently
 * stubbed) read model today; every other case renders the shared
 * "Coming soon" placeholder fragment until its own bounded context and
 * ACL adapter land in a future ticket. This is a presentation concept —
 * which tab is active — not a domain one, so it lives under
 * `Infrastructure/Http`, not `Domain`.
 */
enum MemberHistoryView: string
{
    case Transactions = 'transactions';
    case Activities = 'activities';
    case Memberships = 'memberships';
    case FacilityRentals = 'facility-rentals';
    case EquipmentRentals = 'equipment-rentals';
    case PosPurchases = 'pos-purchases';

    public function label(): string
    {
        return match ($this) {
            self::Transactions => 'Transactions',
            self::Activities => 'Activities',
            self::Memberships => 'Memberships',
            self::FacilityRentals => 'Facility Rentals',
            self::EquipmentRentals => 'Equipment Rentals',
            self::PosPurchases => 'POS Purchases',
        };
    }

    public function isComingSoon(): bool
    {
        return $this !== self::Transactions;
    }
}
