<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Renders a shared "coming soon" stub for every nav destination that does
 * not yet have a real implementation. Each route name uses the ubiquitous
 * staff/admin language (e.g. inventory_index, membership_cards_index) so
 * downstream features can swap their stub action for a real one without
 * also rewiring callers and nav links.
 */
final class PlaceholderController extends AbstractController
{
    #[Route('/cash-register', name: 'cash_register_index', methods: ['GET'])]
    public function cashRegisterIndex(): Response
    {
        return $this->stub('Cash Register');
    }

    #[Route('/cash-register/inventory', name: 'inventory_index', methods: ['GET'])]
    public function inventoryIndex(): Response
    {
        return $this->stub('Inventory');
    }

    #[Route('/cash-register/pos-transactions', name: 'pos_transactions_index', methods: ['GET'])]
    public function posTransactionsIndex(): Response
    {
        return $this->stub('POS Transactions');
    }

    #[Route('/cash-register/payment-plans', name: 'payment_plans_all_index', methods: ['GET'])]
    public function paymentPlansAllIndex(): Response
    {
        return $this->stub('All Payment Plan Invoices');
    }

    #[Route('/cash-register/efts', name: 'eft_all_index', methods: ['GET'])]
    public function eftAllIndex(): Response
    {
        return $this->stub('All EFT Invoices');
    }

    #[Route('/cash-register/gift-cards', name: 'gift_cards_index', methods: ['GET'])]
    public function giftCardsIndex(): Response
    {
        return $this->stub('Gift Cards');
    }

    #[Route('/programs', name: 'programs_index', methods: ['GET'])]
    public function programsIndex(): Response
    {
        return $this->stub('Programs');
    }

    #[Route('/programs/types', name: 'program_types_index', methods: ['GET'])]
    public function programTypesIndex(): Response
    {
        return $this->stub('Program Types');
    }

    #[Route('/programs/payment-plans', name: 'program_payment_plans_index', methods: ['GET'])]
    public function programPaymentPlansIndex(): Response
    {
        return $this->stub('Program Payment Plan Invoices');
    }

    #[Route('/programs/efts', name: 'program_efts_index', methods: ['GET'])]
    public function programEftsIndex(): Response
    {
        return $this->stub('Program EFT Invoices');
    }

    #[Route('/users', name: 'users_index', methods: ['GET'])]
    public function usersIndex(): Response
    {
        return $this->stub('Users');
    }

    #[Route('/users/refunds', name: 'refunds_index', methods: ['GET'])]
    public function refundsIndex(): Response
    {
        return $this->stub('Refunds');
    }

    #[Route('/users/groups', name: 'user_groups_index', methods: ['GET'])]
    public function userGroupsIndex(): Response
    {
        return $this->stub('User Groups');
    }

    #[Route('/memberships', name: 'memberships_index', methods: ['GET'])]
    public function membershipsIndex(): Response
    {
        return $this->stub('Memberships');
    }

    #[Route('/memberships/types', name: 'membership_types_index', methods: ['GET'])]
    public function membershipTypesIndex(): Response
    {
        return $this->stub('Membership Types');
    }

    #[Route('/memberships/logger', name: 'membership_logger_index', methods: ['GET'])]
    public function membershipLoggerIndex(): Response
    {
        return $this->stub('Membership Logger');
    }

    #[Route('/memberships/cards', name: 'membership_cards_index', methods: ['GET'])]
    public function membershipCardsIndex(): Response
    {
        return $this->stub('Membership Cards');
    }

    #[Route('/memberships/payment-plans', name: 'membership_payment_plans_index', methods: ['GET'])]
    public function membershipPaymentPlansIndex(): Response
    {
        return $this->stub('Membership Payment Plan Invoices');
    }

    #[Route('/memberships/efts', name: 'membership_efts_index', methods: ['GET'])]
    public function membershipEftsIndex(): Response
    {
        return $this->stub('Membership EFT Invoices');
    }

    #[Route('/facilities', name: 'facilities_index', methods: ['GET'])]
    public function facilitiesIndex(): Response
    {
        return $this->stub('Facilities');
    }

    #[Route('/facilities/calendar', name: 'facility_calendar_index', methods: ['GET'])]
    public function facilityCalendarIndex(): Response
    {
        return $this->stub('Calendar');
    }

    #[Route('/facilities/rental-codes', name: 'rental_codes_index', methods: ['GET'])]
    public function rentalCodesIndex(): Response
    {
        return $this->stub('Rental Codes');
    }

    #[Route('/facilities/user-rentals', name: 'user_rentals_index', methods: ['GET'])]
    public function userRentalsIndex(): Response
    {
        return $this->stub('Manage User Rentals');
    }

    #[Route('/facilities/payment-plans', name: 'rental_payment_plans_index', methods: ['GET'])]
    public function rentalPaymentPlansIndex(): Response
    {
        return $this->stub('Rental Payment Plan Invoices');
    }

    #[Route('/facilities/efts', name: 'rental_efts_index', methods: ['GET'])]
    public function rentalEftsIndex(): Response
    {
        return $this->stub('Rental EFT Invoices');
    }

    #[Route('/reports', name: 'reports_index', methods: ['GET'])]
    public function reportsIndex(): Response
    {
        return $this->stub('Reports');
    }

    #[Route('/reports/general-ledger', name: 'reports_general_ledger_index', methods: ['GET'])]
    public function reportsGeneralLedgerIndex(): Response
    {
        return $this->stub('General Ledger Reports');
    }

    #[Route('/reports/users', name: 'reports_users_index', methods: ['GET'])]
    public function reportsUsersIndex(): Response
    {
        return $this->stub('User Reports');
    }

    #[Route('/reports/programs', name: 'reports_programs_index', methods: ['GET'])]
    public function reportsProgramsIndex(): Response
    {
        return $this->stub('Program Reports');
    }

    #[Route('/reports/memberships', name: 'reports_memberships_index', methods: ['GET'])]
    public function reportsMembershipsIndex(): Response
    {
        return $this->stub('Membership Reports');
    }

    #[Route('/reports/facilities', name: 'reports_facilities_index', methods: ['GET'])]
    public function reportsFacilitiesIndex(): Response
    {
        return $this->stub('Facility Reports');
    }

    #[Route('/reports/inventory', name: 'reports_inventory_index', methods: ['GET'])]
    public function reportsInventoryIndex(): Response
    {
        return $this->stub('Inventory Reports');
    }

    #[Route('/reports/payment-methods', name: 'reports_payment_method_index', methods: ['GET'])]
    public function reportsPaymentMethodIndex(): Response
    {
        return $this->stub('Payment Method Reports');
    }

    #[Route('/communications', name: 'communications_index', methods: ['GET'])]
    public function communicationsIndex(): Response
    {
        return $this->stub('Communications');
    }

    #[Route('/communications/send-message', name: 'comm_send_message_index', methods: ['GET'])]
    public function commSendMessageIndex(): Response
    {
        return $this->stub('Send Message');
    }

    #[Route('/communications/group-builder', name: 'comm_group_builder_index', methods: ['GET'])]
    public function commGroupBuilderIndex(): Response
    {
        return $this->stub('Group Builder');
    }

    #[Route('/communications/twitter', name: 'comm_twitter_index', methods: ['GET'])]
    public function commTwitterIndex(): Response
    {
        return $this->stub('Twitter');
    }

    #[Route('/communications/settings', name: 'comm_settings_index', methods: ['GET'])]
    public function commSettingsIndex(): Response
    {
        return $this->stub('Communication Settings');
    }

    private function stub(string $sectionTitle): Response
    {
        return $this->render('placeholder/coming_soon.html.twig', [
            'section_title' => $sectionTitle,
        ]);
    }
}
