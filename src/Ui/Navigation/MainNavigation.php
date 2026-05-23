<?php

declare(strict_types=1);

namespace App\Ui\Navigation;

/**
 * The canonical staff-admin main navigation structure. Mirrors the seven
 * top-level categories of the legacy Adobe Flex application (see
 * BlueRecFlashFrontend/pp2flex/src/mainNavBar.mxml, commented-out full
 * menu) so existing operators recognise where every feature lives.
 *
 * The structure lives here — in a single value object — so the Twig
 * component, navigation tests, and any future role-based filter all
 * read from the same source of truth.
 */
final readonly class MainNavigation
{
    /**
     * @param list<NavigationItem> $items
     */
    public function __construct(public array $items)
    {
    }

    public static function build(): self
    {
        return new self([
            new NavigationItem('Cash Register', 'cash_register_index', [
                new NavigationItem('Inventory', 'inventory_index'),
                new NavigationItem('POS Transactions', 'pos_transactions_index'),
                new NavigationItem('All Payment Plan Invoices', 'payment_plans_all_index'),
                new NavigationItem('All EFT Invoices', 'eft_all_index'),
                new NavigationItem('Gift Cards', 'gift_cards_index'),
            ]),
            new NavigationItem('Programs', 'programs_index', [
                new NavigationItem('Program Types', 'program_types_index'),
                new NavigationItem('Program Payment Plan Invoices', 'program_payment_plans_index'),
                new NavigationItem('Program EFT Invoices', 'program_efts_index'),
            ]),
            new NavigationItem('Users', 'users_index', [
                new NavigationItem('Refunds', 'refunds_index'),
                new NavigationItem('User Groups', 'user_groups_index'),
            ]),
            new NavigationItem('Memberships', 'memberships_index', [
                new NavigationItem('Membership Types', 'membership_types_index'),
                new NavigationItem('Membership Logger', 'membership_logger_index'),
                new NavigationItem('Membership Cards', 'membership_cards_index'),
                new NavigationItem('Membership Payment Plan Invoices', 'membership_payment_plans_index'),
                new NavigationItem('Membership EFT Invoices', 'membership_efts_index'),
            ]),
            new NavigationItem('Facilities', 'facilities_index', [
                new NavigationItem('Calendar', 'facility_calendar_index'),
                new NavigationItem('Rental Codes', 'rental_codes_index'),
                new NavigationItem('Manage User Rentals', 'user_rentals_index'),
                new NavigationItem('Rental Payment Plan Invoices', 'rental_payment_plans_index'),
                new NavigationItem('Rental EFT Invoices', 'rental_efts_index'),
            ]),
            new NavigationItem('Reports', 'reports_index', [
                new NavigationItem('General Ledger Reports', 'reports_general_ledger_index'),
                new NavigationItem('User Reports', 'reports_users_index'),
                new NavigationItem('Program Reports', 'reports_programs_index'),
                new NavigationItem('Membership Reports', 'reports_memberships_index'),
                new NavigationItem('Facility Reports', 'reports_facilities_index'),
                new NavigationItem('Inventory Reports', 'reports_inventory_index'),
                new NavigationItem('Payment Method Reports', 'reports_payment_method_index'),
            ]),
            new NavigationItem('Communications', 'communications_index', [
                new NavigationItem('Send Message', 'comm_send_message_index'),
                new NavigationItem('Group Builder', 'comm_group_builder_index'),
                new NavigationItem('Twitter', 'comm_twitter_index'),
                new NavigationItem('Communication Settings', 'comm_settings_index'),
            ]),
        ]);
    }
}
