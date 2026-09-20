<?php

declare(strict_types=1);

namespace App\Administration\Domain;

/**
 * The catalogue of named permissions the application checks — the closed,
 * code-defined vocabulary a {@see PrivilegeLookup} resolves a stored or
 * requested name against.
 *
 * This enum is the only vocabulary any privilege check may use, which is
 * what makes "unknown privilege" a type error at every call site that
 * names one literally. An organization assigns privileges to a role; it
 * does not define which privileges exist — that authority lives here,
 * versioned with the application, not as per-organization rows an
 * operator can add to.
 *
 * Backing values follow the legacy BlueRec shape exactly (verb first, no
 * group prefix, SCREAMING_SNAKE) because most of these names are shared
 * between the two reference agencies reconciled in
 * docs/authorization/privilege-reconciliation.md, and re-spelling them
 * would throw away that reconciliation's join key. A case's backing
 * value is contract surface once shipped and is never repurposed —
 * grants recorded later store this value as a plain string, so removing
 * a case is a safe, fail-closed operation (a stored value that no
 * longer matches a case denies via {@see PrivilegeLookup}), but changing
 * what an existing value means is not.
 *
 * Ship a case only where the capability it governs exists in the
 * application today or is built by this epic. Every legacy privilege not
 * yet shipped is recorded in the reconciliation doc with the context
 * that will own it; that context's own ticket adds the case when it
 * ships the capability.
 *
 * Adding a privilege is one file and two adjacent edits: a case here and
 * its arm in {@see self::definition()}. PHPStan level 9 fails the build
 * on a missing arm, so the two edits cannot drift apart. Do not add a
 * separate lookup array (two edit sites) or reflection attributes (pulls
 * reflection into Domain and loses static exhaustiveness).
 */
enum Privilege: string
{
    // Administration folder. Legacy filed the first four under its Users
    // folder; they move here because administrators are no longer part
    // of the Users (login-credential) context. ViewPrivilegeAudit is
    // net-new — no legacy predecessor — seeded here for LRA-273's review
    // screen.
    case ViewAdmins = 'VIEW_ADMINS';
    case AddAdmins = 'ADD_ADMINS';
    case RemoveAdmins = 'REMOVE_ADMINS';
    case ManageAdminRanks = 'MANAGE_ADMIN_RANKS';
    case ViewPrivilegeAudit = 'VIEW_PRIVILEGE_AUDIT';

    // Users folder: household/member record administration (a Households
    // context capability — see PrivilegeGroup::Users docblock).
    case ViewUsers = 'VIEW_USERS';
    case AddUsers = 'ADD_USERS';
    case EditUsers = 'EDIT_USERS';
    case RemoveUsers = 'REMOVE_USERS';
    case MergeUsers = 'MERGE_USERS';
    case MoveUsersFromHousehold = 'MOVE_USERS_FROM_HOUSEHOLD';
    case ChangeResidencyDate = 'CHANGE_RESIDENCY_DATE';
    case ImportUsers = 'IMPORT_USERS';
    case ViewUserGroups = 'VIEW_USER_GROUPS';
    case AddUserGroups = 'ADD_USER_GROUPS';
    case EditUserGroups = 'EDIT_USER_GROUPS';

    // Inventory folder.
    case ViewInventory = 'VIEW_INVENTORY';
    case AddInventory = 'ADD_INVENTORY';
    case EditInventory = 'EDIT_INVENTORY';
    case EnterInventory = 'ENTER_INVENTORY';
    case AcceptInventoryReceipt = 'ACCEPT_INVENTORY_RECEIPT';
    case ViewEquipment = 'VIEW_EQUIPMENT';
    case AddEquipment = 'ADD_EQUIPMENT';
    case EditEquipment = 'EDIT_EQUIPMENT';
    case OverridePosPriceRestriction = 'OVERRIDE_POS_PRICE_RESTRICTION';

    // Cash Register folder. PosRefunds is the one CSUSAC-only name in
    // the union of both reference agencies' catalogues; every other
    // case in this folder is Bellevue's Cash Register group verbatim.
    case SellInventory = 'SELL_INVENTORY';
    case SellMemberships = 'SELL_MEMBERSHIPS';
    case SellPrograms = 'SELL_PROGRAMS';
    case SellReservations = 'SELL_RESERVATIONS';
    case SellRentals = 'SELL_RENTALS';
    case ViewPosTransactions = 'VIEW_POS_TRANSACTIONS';
    case PosRefunds = 'POS_REFUNDS';
    case NoSale = 'NO_SALE';
    case SaveTab = 'SAVE_TAB';
    case RecallLocationTabs = 'RECALL_LOCATION_TABS';
    case RecallAnyTab = 'RECALL_ANY_TAB';
    case AddBalanceTransactions = 'ADD_BALANCE_TRANSACTIONS';
    case EftPayments = 'EFT_PAYMENTS';
    case CrGlEntry = 'CR_GL_ENTRY';
    case ManageGiftCards = 'MANAGE_GIFT_CARDS';
    case OverrideProgramFees = 'OVERRIDE_PROGRAM_FEES';
    case OverrideInventoryFees = 'OVERRIDE_INVENTORY_FEES';
    case OverrideReservationFees = 'OVERRIDE_RESERVATION_FEES';
    case OverrideMembershipFees = 'OVERRIDE_MEMBERSHIP_FEES';
    case OverrideMembershipRequirements = 'OVERRIDE_MEMBERSHIP_REQUIREMENTS';
    case OverrideProgramRequirements = 'OVERRIDE_PROGRAM_REQUIREMENTS';
    case OverrideAge = 'OVERRIDE_AGE';
    case OverbookPrograms = 'OVERBOOK_PROGRAMS';
    case ProgramOverbooking = 'PROGRAM_OVERBOOKING';
    case WaitlistProgramBeforeFull = 'WAITLIST_PROGRAM_BEFORE_FULL';
    case DuplicateRegistrations = 'DUPLICATE_REGISTRATIONS';
    case JdeCisAmanda = 'JDE_CIS_AMANDA';

    /**
     * The screen metadata for this privilege: display name, description,
     * folder, display order within that folder, and audit risk. One
     * match arm per case — see the class docblock for why this replaces
     * a lookup array or attributes.
     */
    public function definition(): PrivilegeDefinition
    {
        return match ($this) {
            self::ViewAdmins => new PrivilegeDefinition(
                'View Admins',
                'View the list of administrator accounts and their assigned ranks.',
                PrivilegeGroup::Administration,
                0,
            ),
            self::AddAdmins => new PrivilegeDefinition(
                'Add Admins',
                'Create a new administrator account.',
                PrivilegeGroup::Administration,
                1,
            ),
            self::RemoveAdmins => new PrivilegeDefinition(
                'Remove Admins',
                'Deactivate or remove an administrator account.',
                PrivilegeGroup::Administration,
                2,
            ),
            self::ManageAdminRanks => new PrivilegeDefinition(
                'Manage Admin Ranks',
                'Create, edit, or delete administrator ranks and the privilege sets they carry.',
                PrivilegeGroup::Administration,
                3,
            ),
            self::ViewPrivilegeAudit => new PrivilegeDefinition(
                'View Privilege Audit',
                'View the audit log of denied and high-risk privilege checks.',
                PrivilegeGroup::Administration,
                4,
            ),
            self::ViewUsers => new PrivilegeDefinition(
                'View Users',
                'View household and member records.',
                PrivilegeGroup::Users,
                0,
            ),
            self::AddUsers => new PrivilegeDefinition(
                'Add Users',
                'Register a new household or add a member to an existing household.',
                PrivilegeGroup::Users,
                1,
            ),
            self::EditUsers => new PrivilegeDefinition(
                'Edit Users',
                "Edit a household or member's profile details.",
                PrivilegeGroup::Users,
                2,
            ),
            self::RemoveUsers => new PrivilegeDefinition(
                'Remove Users',
                'Deactivate or remove a member record.',
                PrivilegeGroup::Users,
                3,
            ),
            self::MergeUsers => new PrivilegeDefinition(
                'Merge User Records',
                'Merge duplicate member records into one.',
                PrivilegeGroup::Users,
                4,
            ),
            self::MoveUsersFromHousehold => new PrivilegeDefinition(
                'Remove/Move Users from Household',
                'Move or remove a member from a household.',
                PrivilegeGroup::Users,
                5,
            ),
            self::ChangeResidencyDate => new PrivilegeDefinition(
                'Set Specific Residency Date',
                "Set a member's residency status effective on a specific date rather than today.",
                PrivilegeGroup::Users,
                6,
            ),
            self::ImportUsers => new PrivilegeDefinition(
                'Import Users',
                'Bulk-import household and member records from a file.',
                PrivilegeGroup::Users,
                7,
            ),
            self::ViewUserGroups => new PrivilegeDefinition(
                'View User Groups',
                'View the groups a member or household belongs to.',
                PrivilegeGroup::Users,
                8,
            ),
            self::AddUserGroups => new PrivilegeDefinition(
                'Add User Groups',
                'Create a new member or household group.',
                PrivilegeGroup::Users,
                9,
            ),
            self::EditUserGroups => new PrivilegeDefinition(
                'Edit User Groups',
                'Edit an existing member or household group.',
                PrivilegeGroup::Users,
                10,
            ),
            self::ViewInventory => new PrivilegeDefinition(
                'View Inventory Items',
                'View inventory items and their stock levels.',
                PrivilegeGroup::Inventory,
                0,
            ),
            self::AddInventory => new PrivilegeDefinition(
                'Add Inventory Items',
                'Create a new inventory item.',
                PrivilegeGroup::Inventory,
                1,
            ),
            self::EditInventory => new PrivilegeDefinition(
                'Edit Inventory Items',
                'Edit an existing inventory item.',
                PrivilegeGroup::Inventory,
                2,
            ),
            self::EnterInventory => new PrivilegeDefinition(
                'Enter Inventory',
                'Record new stock received into inventory.',
                PrivilegeGroup::Inventory,
                3,
            ),
            self::AcceptInventoryReceipt => new PrivilegeDefinition(
                'Accept Inventory Receipt',
                'Accept a delivered purchase order receipt into inventory.',
                PrivilegeGroup::Inventory,
                4,
            ),
            self::ViewEquipment => new PrivilegeDefinition(
                'View Equipment',
                'View rental equipment records.',
                PrivilegeGroup::Inventory,
                5,
            ),
            self::AddEquipment => new PrivilegeDefinition(
                'Add Equipment',
                'Create a new rental equipment record.',
                PrivilegeGroup::Inventory,
                6,
            ),
            self::EditEquipment => new PrivilegeDefinition(
                'Edit Equipment',
                'Edit an existing rental equipment record.',
                PrivilegeGroup::Inventory,
                7,
            ),
            self::OverridePosPriceRestriction => new PrivilegeDefinition(
                'Allow Overriding of POS Price Restrictions',
                'Override a point-of-sale restriction that would otherwise block a price change.',
                PrivilegeGroup::Inventory,
                8,
            ),
            self::SellInventory => new PrivilegeDefinition(
                'Inventory Sales',
                'Sell inventory items at the point of sale.',
                PrivilegeGroup::CashRegister,
                0,
            ),
            self::SellMemberships => new PrivilegeDefinition(
                'Membership Sales',
                'Sell memberships at the point of sale.',
                PrivilegeGroup::CashRegister,
                1,
            ),
            self::SellPrograms => new PrivilegeDefinition(
                'Program Registrations',
                'Register participants into programs at the point of sale.',
                PrivilegeGroup::CashRegister,
                2,
            ),
            self::SellReservations => new PrivilegeDefinition(
                'Reservations',
                'Sell facility reservations at the point of sale.',
                PrivilegeGroup::CashRegister,
                3,
            ),
            self::SellRentals => new PrivilegeDefinition(
                'Sell Equipment Rentals',
                'Sell equipment rentals at the point of sale.',
                PrivilegeGroup::CashRegister,
                4,
            ),
            self::ViewPosTransactions => new PrivilegeDefinition(
                'View POS Transactions',
                'View completed point-of-sale transactions.',
                PrivilegeGroup::CashRegister,
                5,
            ),
            self::PosRefunds => new PrivilegeDefinition(
                'POS Refunds',
                'Process a refund at the point of sale.',
                PrivilegeGroup::CashRegister,
                6,
                PrivilegeRisk::High,
            ),
            self::NoSale => new PrivilegeDefinition(
                'No Sale',
                "Open the cash drawer without completing a sale.",
                PrivilegeGroup::CashRegister,
                7,
            ),
            self::SaveTab => new PrivilegeDefinition(
                'Save Tab',
                'Hold an in-progress transaction in a tab and recall it later.',
                PrivilegeGroup::CashRegister,
                8,
            ),
            self::RecallLocationTabs => new PrivilegeDefinition(
                'Recall Location Tabs',
                "Recall any held tab at the operator's own facility.",
                PrivilegeGroup::CashRegister,
                9,
            ),
            self::RecallAnyTab => new PrivilegeDefinition(
                'Recall Any Tab',
                'Recall any held tab at any facility.',
                PrivilegeGroup::CashRegister,
                10,
            ),
            self::AddBalanceTransactions => new PrivilegeDefinition(
                'Add Balance Transactions',
                "Process a transaction that leaves a member's account balance negative.",
                PrivilegeGroup::CashRegister,
                11,
            ),
            self::EftPayments => new PrivilegeDefinition(
                'Setup EFT Payments',
                'Set up electronic funds transfer payment schedules.',
                PrivilegeGroup::CashRegister,
                12,
            ),
            self::CrGlEntry => new PrivilegeDefinition(
                'GL Entry Transactions',
                'Post manual general-ledger entries from the cash register.',
                PrivilegeGroup::CashRegister,
                13,
                PrivilegeRisk::High,
            ),
            self::ManageGiftCards => new PrivilegeDefinition(
                'Manage Gift Cards',
                'Issue or adjust gift cards.',
                PrivilegeGroup::CashRegister,
                14,
            ),
            self::OverrideProgramFees => new PrivilegeDefinition(
                'Override Program Fees',
                "Override a program's listed fee at the point of sale.",
                PrivilegeGroup::CashRegister,
                15,
                PrivilegeRisk::High,
            ),
            self::OverrideInventoryFees => new PrivilegeDefinition(
                'Override Inventory Fees',
                "Override an inventory item's listed price at the point of sale.",
                PrivilegeGroup::CashRegister,
                16,
                PrivilegeRisk::High,
            ),
            self::OverrideReservationFees => new PrivilegeDefinition(
                'Override Reservation Fees',
                "Override a facility reservation's listed fee at the point of sale.",
                PrivilegeGroup::CashRegister,
                17,
                PrivilegeRisk::High,
            ),
            self::OverrideMembershipFees => new PrivilegeDefinition(
                'Override Membership Fees',
                "Override a membership's listed fee at the point of sale.",
                PrivilegeGroup::CashRegister,
                18,
                PrivilegeRisk::High,
            ),
            self::OverrideMembershipRequirements => new PrivilegeDefinition(
                'Override Membership Requirements',
                'Sell a membership despite failing its eligibility requirements.',
                PrivilegeGroup::CashRegister,
                19,
            ),
            self::OverrideProgramRequirements => new PrivilegeDefinition(
                'Override Program Requirements',
                "Register a participant despite failing a program's eligibility requirements.",
                PrivilegeGroup::CashRegister,
                20,
            ),
            self::OverrideAge => new PrivilegeDefinition(
                'Override Age Requirements',
                "Override a program or facility's minimum or maximum age requirement.",
                PrivilegeGroup::CashRegister,
                21,
            ),
            self::OverbookPrograms => new PrivilegeDefinition(
                'Overbook Programs',
                'Register a participant into a program that is already at capacity.',
                PrivilegeGroup::CashRegister,
                22,
            ),
            self::ProgramOverbooking => new PrivilegeDefinition(
                'Allow Program Overbooking',
                "Allow a program to accept registrations beyond its listed capacity.",
                PrivilegeGroup::CashRegister,
                23,
            ),
            self::WaitlistProgramBeforeFull => new PrivilegeDefinition(
                'Waitlist Program Before Full',
                "Add a participant to a program's waitlist before the program is full.",
                PrivilegeGroup::CashRegister,
                24,
            ),
            self::DuplicateRegistrations => new PrivilegeDefinition(
                'Create Duplicate Program Registrations',
                'Register the same participant into a program more than once.',
                PrivilegeGroup::CashRegister,
                25,
            ),
            self::JdeCisAmanda => new PrivilegeDefinition(
                'JDE/CIS/AMANDA Interface',
                'Access the JDE/CIS/AMANDA municipal finance interface.',
                PrivilegeGroup::CashRegister,
                26,
            ),
        };
    }
}
