# Privilege reconciliation

LRA-266 ships the privilege catalogue as product-defined constants
(`App\Administration\Domain\Privilege`) instead of per-organization
database rows. This document is the one-time reconciliation of every
named permission the two available legacy reference agencies define,
so a reviewer can see exactly which legacy names became a `Privilege`
case, which are deferred to a named future bounded-context ticket, and
which are dropped outright with a written reason.

No legacy data is migrated by this ticket or this document. Both
databases below are read-only reference copies queried for evidence;
nothing here becomes an import step, and no organization's assignment
of privileges to ranks is carried forward — only the *names* an
organization could assign from.

## Source data and population

Queried against both legacy reference copies on the `desktop-linux`
Docker Desktop compose stack (`literec-main`, service `legacy-db`,
read-only, `parkpro_ol_user`):

```sql
-- Row and group counts per agency
SELECT COUNT(*) FROM privileges;                          -- bluerec_bellevue_wa: 175 rows
SELECT COUNT(DISTINCT group_id) FROM privileges;           -- bluerec_bellevue_wa: 13 groups in use
SELECT COUNT(*) FROM privileges;                          -- bluerec_csusac: 103 rows
SELECT COUNT(DISTINCT group_id) FROM privileges;           -- bluerec_csusac: 9 groups in use

-- Full per-agency listing joined to its group name
SELECT p.priv_name, p.priv_display_name, g.group_name, p.priv_order
FROM privileges p JOIN privilege_groups g ON g.group_id = p.group_id
ORDER BY g.group_order, p.priv_name;

-- Distinct-name overlap between the two agencies
SELECT COUNT(*) FROM
  (SELECT DISTINCT priv_name FROM bluerec_bellevue_wa.privileges) b
  JOIN (SELECT DISTINCT priv_name FROM bluerec_csusac.privileges) c
    ON b.priv_name = c.priv_name;                          -- 102 shared
-- LEFT JOIN with c.priv_name IS NULL for Bellevue-only (68), and the
-- mirrored query for CSUSAC-only (1: POS_REFUNDS).
```

**Verified population: 171 distinct privilege names** across both
agencies — 170 distinct names in Bellevue's 175 raw rows (`privileges`
carries five duplicate `priv_name` rows under the same `group_id`:
`CANCEL_PROGRAMS`, `PROGRAM_PARTICIPANT_ROLLOVER`, `MEMBERSHIP_PAYROLL`,
`MANAGE_CUSTOM_FIELDS`, `LOCATION_INTERNET_SETTINGS` — a legacy data
quality artifact, not a modelling signal), plus the one CSUSAC-only
name (`POS_REFUNDS`). 102 names are shared between the two agencies; 68
are Bellevue-only; 1 (`POS_REFUNDS`) is CSUSAC-only.

Bellevue's `privilege_groups` table defines 15 folders (matching the
originally reported group count), but only 13 have any privilege
assigned in either agency's data — `General Ledgers` and `Discounts`
are named in the schema and carry zero rows in both reference copies,
so neither appears in the table below. A future ticket introducing
either capability adds privileges with no legacy predecessor, the same
way `VIEW_PRIVILEGE_AUDIT` is added below.

Legacy row shape: `priv_name varchar(45)`, `priv_display_name
varchar(45)`, `priv_description text` (empty for all but one legacy
row — `ADD_BALANCE_TRANSACTIONS` — so every description on the new
`Privilege::definition()` is freshly authored for this catalogue, not
copied from legacy), `group_id`, `priv_order tinyint` (always `0` in
both agencies — legacy carries no usable intra-group ordering, so the
`order` on each new `PrivilegeDefinition` is this ticket's own
assignment, not a legacy value).

CSUSAC files five names — `ADD_REFUNDS`, `EDIT_REFUNDS`, `POS_REFUNDS`,
`PROCESS_REFUNDS`, `VIEW_REFUNDS` — plus three `PRINT_*_REPORTS` names
under its own `Users` folder where Bellevue files the refund names
under `Accounting` and the report names under `Reports`. The
`legacy_group` column below is Bellevue's canonical placement (Bellevue
defines all 15 folders; CSUSAC's folder assignment is organization-local
noise, not a modelling signal) — `POS_REFUNDS` alone has no Bellevue
row and is marked `(CSUSAC only)`.

## The worked example this ticket fixes

Bellevue defines `OVERRIDE_PROGRAM_FEES`, `OVERRIDE_INVENTORY_FEES`,
`OVERRIDE_RESERVATION_FEES` and `OVERRIDE_MEMBERSHIP_FEES`. CSUSAC
defines none of the four (confirmed above: all four are Bellevue-only,
present in the `Cash Register` folder). The legacy till price-override
check enables editing when those four rights are not all present on
the administrator record, so at CSUSAC every operator can retype any
price — not by policy, but because the permissions that would restrict
it were never created there.

All four ship as cases now: `Privilege::OverrideProgramFees`,
`Privilege::OverrideInventoryFees`, `Privilege::OverrideReservationFees`,
`Privilege::OverrideMembershipFees`, each declared `PrivilegeRisk::High`.
Because the catalogue is code, versioned with the application, no
organization can silently lack a permission that restricts a sensitive
capability — an organization can decline to *grant* one of these
privileges to a rank, but the privilege itself always exists to be
granted or withheld, and an unrecognised or absent privilege denies by
default (`PrivilegeLookup::privilegeNamed()` throws `UnknownPrivilege`
rather than returning null).

## Which privileges ship now

52 cases ship in this ticket: the full legacy `Users` (15), `Inventory`
(9) and `Cash Register` (26) folder contents, de-duplicated across the
two agencies, plus the one CSUSAC-only `POS_REFUNDS`, plus one net-new
case. `VIEW_ADMINS`, `ADD_ADMINS`, `REMOVE_ADMINS` and
`MANAGE_ADMIN_RANKS` move out of the legacy `Users` folder into a new
`PrivilegeGroup::Administration` folder, because administrators are no
longer part of the `Users` (login-credential) bounded context;
`VIEW_PRIVILEGE_AUDIT` is seeded alongside them, net-new, for LRA-273's
review screen. The remaining 11 `Users`-folder names stay filed under
`PrivilegeGroup::Users` (household/member record administration — a
Households context capability; see the `PrivilegeGroup` docblock for
why the folder label differs from the bounded context that implements
it).

High-risk set (`PrivilegeRisk::High`), pinned by a dedicated unit test
so a later edit cannot quietly drop one from LRA-273's audit stream:
the four `OVERRIDE_*_FEES` cases above, `PosRefunds` (the only
refund-capable privilege in the shipped set), and `CrGlEntry` (manual
general-ledger postings from the register).

## Full reconciliation

One row per distinct legacy privilege name across both agencies (171
rows). Disposition is exactly one of: shipped as a case now (with the
case name), deferred to a named future context, or dropped with a
written reason.

| Legacy name | Bellevue | CSUSAC | Legacy group | Disposition |
|---|---|---|---|---|
| `ACCEPT_INVENTORY_RECEIPT` | Yes | No | Inventory | Shipped as a case now: `Privilege::AcceptInventoryReceipt` |
| `ACCEPT_VOLUNTEERS` | Yes | Yes | Programs | Deferred to a future Programs context ticket |
| `ADD_ADMINS` | Yes | Yes | Users | Shipped as a case now: `Privilege::AddAdmins` |
| `ADD_BALANCE_TRANSACTIONS` | Yes | Yes | Cash Register | Shipped as a case now: `Privilege::AddBalanceTransactions` |
| `ADD_CALENDAR_EVENTS` | Yes | Yes | Facilities | Deferred to a future Facilities context ticket |
| `ADD_EQUIPMENT` | Yes | Yes | Inventory | Shipped as a case now: `Privilege::AddEquipment` |
| `ADD_FACILITIES` | Yes | Yes | Facilities | Deferred to a future Facilities context ticket |
| `ADD_FEE_TYPES` | Yes | Yes | Fee Types | Deferred to a future Fee Types context ticket |
| `ADD_INVENTORY` | Yes | Yes | Inventory | Shipped as a case now: `Privilege::AddInventory` |
| `ADD_JOURNAL_ENTRIES` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `ADD_LOCATIONS` | Yes | Yes | Facilities | Deferred to a future Facilities context ticket |
| `ADD_MEMBERSHIPS` | Yes | Yes | Memberships | Deferred to a future Memberships context ticket |
| `ADD_MEMBERSHIP_CARDS` | Yes | Yes | Memberships | Deferred to a future Memberships context ticket |
| `ADD_MEMBERSHIP_TYPES` | Yes | Yes | Memberships | Deferred to a future Memberships context ticket |
| `ADD_PROGRAMS` | Yes | Yes | Programs | Deferred to a future Programs context ticket |
| `ADD_PROGRAM_TYPES` | Yes | Yes | Programs | Deferred to a future Programs context ticket |
| `ADD_REFUNDS` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `ADD_TAX` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `ADD_USERS` | Yes | Yes | Users | Shipped as a case now: `Privilege::AddUsers` |
| `ADD_USER_GROUPS` | Yes | Yes | Users | Shipped as a case now: `Privilege::AddUserGroups` |
| `ADD_VOLUNTEERS` | Yes | Yes | Programs | Deferred to a future Programs context ticket |
| `ALL_EFT_INVOICES` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `ALL_PAYMENT_INVOICES` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `APPROVE_REFUNDS` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `AR_REPORTS` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `ASSIGN_VOLUNTEER_ROLES` | Yes | Yes | Programs | Deferred to a future Programs context ticket |
| `CANCEL_PROGRAMS` | Yes | No | Programs | Deferred to a future Programs context ticket |
| `CHANGE_MEMBERSHIP_WITHOUT_TRANSFER` | Yes | Yes | Memberships | Deferred to a future Memberships context ticket |
| `CHANGE_PROGRAM_CODE` | Yes | No | Programs | Deferred to a future Programs context ticket |
| `CHANGE_RESIDENCY_DATE` | Yes | Yes | Users | Shipped as a case now: `Privilege::ChangeResidencyDate` |
| `CLOSING_REPORTS` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `CLOSING_REPORTS_DETAILED` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `CREATE_COMMUNICATION_EMAILS` | Yes | Yes | Communications | Deferred to a future Communications context ticket |
| `CREATE_COMMUNICATION_GROUPS` | Yes | Yes | Communications | Deferred to a future Communications context ticket |
| `CREATE_SCHOLARSHIP` | Yes | No | Scholarships | Deferred to a future Scholarships context ticket |
| `CREDIT_CARD_REPORTS` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `CR_GL_ENTRY` | Yes | No | Cash Register | Shipped as a case now: `Privilege::CrGlEntry` |
| `DELETE_COMMUNICATION_GROUPS` | Yes | Yes | Communications | Deferred to a future Communications context ticket |
| `DELETE_SCHOLARSHIP` | Yes | No | Scholarships | Deferred to a future Scholarships context ticket |
| `DUPLICATE_REGISTRATIONS` | Yes | Yes | Cash Register | Shipped as a case now: `Privilege::DuplicateRegistrations` |
| `EDIT_ALL` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `EDIT_COMMUNICATION_SETTINGS` | Yes | Yes | Communications | Deferred to a future Communications context ticket |
| `EDIT_EQUIPMENT` | Yes | Yes | Inventory | Shipped as a case now: `Privilege::EditEquipment` |
| `EDIT_FACILITIES` | Yes | Yes | Facilities | Deferred to a future Facilities context ticket |
| `EDIT_FEE_TYPES` | Yes | Yes | Fee Types | Deferred to a future Fee Types context ticket |
| `EDIT_INVENTORY` | Yes | Yes | Inventory | Shipped as a case now: `Privilege::EditInventory` |
| `EDIT_LOCATIONS` | Yes | Yes | Facilities | Deferred to a future Facilities context ticket |
| `EDIT_MEMBERSHIPS` | Yes | Yes | Memberships | Deferred to a future Memberships context ticket |
| `EDIT_MEMBERSHIP_CARDS` | Yes | Yes | Memberships | Deferred to a future Memberships context ticket |
| `EDIT_MEMBERSHIP_TYPES` | Yes | Yes | Memberships | Deferred to a future Memberships context ticket |
| `EDIT_PAST_PAYMENT` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `EDIT_PROGRAMS` | Yes | Yes | Programs | Deferred to a future Programs context ticket |
| `EDIT_PROGRAM_TYPES` | Yes | Yes | Programs | Deferred to a future Programs context ticket |
| `EDIT_REFUNDS` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `EDIT_SAME_DAY` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `EDIT_SCHOLARSHIP` | Yes | No | Scholarships | Deferred to a future Scholarships context ticket |
| `EDIT_TAX` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `EDIT_TRANSACTION_COMMENTS` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `EDIT_TRANSACTION_DATE` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `EDIT_TRANSACTION_PAYER` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `EDIT_USERS` | Yes | Yes | Users | Shipped as a case now: `Privilege::EditUsers` |
| `EDIT_USER_GROUPS` | Yes | Yes | Users | Shipped as a case now: `Privilege::EditUserGroups` |
| `EFT_PAYMENTS` | Yes | No | Cash Register | Shipped as a case now: `Privilege::EftPayments` |
| `ENTER_INVENTORY` | Yes | No | Inventory | Shipped as a case now: `Privilege::EnterInventory` |
| `EXPORT_BROCHURE` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `FACEBOOK` | Yes | Yes | Communications | Dropped: Legacy Facebook wall-posting integration for the defunct ParkPro2Flex Communications module; the platform API it depended on no longer supports this integration style and no successor screen is planned. |
| `FACILITY_REPORTS` | Yes | Yes | Reports | Deferred to a future Reports context ticket |
| `FUTURE_REVENUE_REPORTS` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `GENERAL_LEDGER_REPORTS` | Yes | Yes | Reports | Deferred to a future Reports context ticket |
| `IMPORT_USERS` | Yes | Yes | Users | Shipped as a case now: `Privilege::ImportUsers` |
| `INSTRUCTOR_DETAIL_REPORTS` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `INVENTORY_REPORTS` | Yes | Yes | Reports | Deferred to a future Reports context ticket |
| `JDE_CIS_AMANDA` | Yes | No | Cash Register | Shipped as a case now: `Privilege::JdeCisAmanda` |
| `LOCATION_INTERNET_SETTINGS` | Yes | No | Internet | Deferred to a future Internet context ticket |
| `MANAGE_ADMIN_RANKS` | Yes | Yes | Users | Shipped as a case now: `Privilege::ManageAdminRanks` |
| `MANAGE_CUSTOM_FIELDS` | Yes | No | Customization | Deferred to a future Customization context ticket |
| `MANAGE_CUSTOM_FIELD_GROUPS` | Yes | No | Customization | Deferred to a future Customization context ticket |
| `MANAGE_FACILITY_STAFF` | Yes | No | Facilities | Deferred to a future Facilities context ticket |
| `MANAGE_GIFT_CARDS` | Yes | Yes | Cash Register | Shipped as a case now: `Privilege::ManageGiftCards` |
| `MANAGE_LEDGER_ACCOUNTS` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `MANAGE_PAYMENT_METHODS` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `MANAGE_PROGRAM_WAITLIST` | Yes | No | Programs | Deferred to a future Programs context ticket |
| `MANAGE_RENTAL_CODES` | Yes | Yes | Facilities | Deferred to a future Facilities context ticket |
| `MANAGE_TAX_RATE` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `MANAGE_USER_MEMBERSHIP_EXPIRATION` | Yes | No | Memberships | Deferred to a future Memberships context ticket |
| `MANAGE_USER_MEMBERSHIP_UNITS` | Yes | No | Memberships | Deferred to a future Memberships context ticket |
| `MANAGE_USER_RENTALS` | Yes | Yes | Facilities | Deferred to a future Facilities context ticket |
| `MEMBERSHIP_EFT_INVOICES` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `MEMBERSHIP_PAYMENT_INVOICES` | Yes | Yes | Memberships | Deferred to a future Memberships context ticket |
| `MEMBERSHIP_PAYROLL` | Yes | No | Memberships | Deferred to a future Memberships context ticket |
| `MEMBERSHIP_REPORTS` | Yes | Yes | Reports | Deferred to a future Reports context ticket |
| `MERGE_USERS` | Yes | Yes | Users | Shipped as a case now: `Privilege::MergeUsers` |
| `MOVE_USERS_FROM_HOUSEHOLD` | Yes | Yes | Users | Shipped as a case now: `Privilege::MoveUsersFromHousehold` |
| `NO_SALE` | Yes | No | Cash Register | Shipped as a case now: `Privilege::NoSale` |
| `ORG_INTERNET_SETTINGS` | Yes | No | Internet | Deferred to a future Internet context ticket |
| `OVERBOOK_PROGRAMS` | Yes | Yes | Cash Register | Shipped as a case now: `Privilege::OverbookPrograms` |
| `OVERRIDE_AGE` | Yes | Yes | Cash Register | Shipped as a case now: `Privilege::OverrideAge` |
| `OVERRIDE_INVENTORY_FEES` | Yes | No | Cash Register | Shipped as a case now: `Privilege::OverrideInventoryFees` |
| `OVERRIDE_MEMBERSHIP_FEES` | Yes | No | Cash Register | Shipped as a case now: `Privilege::OverrideMembershipFees` |
| `OVERRIDE_MEMBERSHIP_REQUIREMENTS` | Yes | No | Cash Register | Shipped as a case now: `Privilege::OverrideMembershipRequirements` |
| `OVERRIDE_POS_PRICE_RESTRICTION` | Yes | Yes | Inventory | Shipped as a case now: `Privilege::OverridePosPriceRestriction` |
| `OVERRIDE_PROGRAM_FEES` | Yes | No | Cash Register | Shipped as a case now: `Privilege::OverrideProgramFees` |
| `OVERRIDE_PROGRAM_REQUIREMENTS` | Yes | No | Cash Register | Shipped as a case now: `Privilege::OverrideProgramRequirements` |
| `OVERRIDE_RESERVATION_FEES` | Yes | No | Cash Register | Shipped as a case now: `Privilege::OverrideReservationFees` |
| `OVERRIDE_RESERVATION_HOURS` | Yes | Yes | Facilities | Deferred to a future Facilities context ticket |
| `OVERRIDE_SCHEDULING_CONFLICTS` | Yes | Yes | Facilities | Deferred to a future Facilities context ticket |
| `PAYMENT_METHOD_REPORTS` | Yes | Yes | Reports | Deferred to a future Reports context ticket |
| `POS_LAYOUT` | Yes | No | Customization | Deferred to a future Customization context ticket |
| `POS_REFUNDS` | No | Yes | (CSUSAC only) | Shipped as a case now: `Privilege::PosRefunds` |
| `PRINT_ALL_ADMIN_REPORTS` | Yes | Yes | Reports | Deferred to a future Reports context ticket |
| `PRINT_ALL_POS_REPORTS` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `PRINT_LOCATION_POS_REPORTS` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `PRINT_MEMBERSHIP_CARDS` | Yes | Yes | Reports | Deferred to a future Reports context ticket |
| `PRINT_OWN_POS_REPORTS` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `PRINT_SAME_LEVEL_REPORTS` | Yes | Yes | Reports | Deferred to a future Reports context ticket |
| `PRINT_SAME_RANK_REPORTS` | Yes | Yes | Reports | Deferred to a future Reports context ticket |
| `PROCESS_EFT_PAYMENT` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `PROCESS_REFUNDS` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `PROGRAM_EFT_INVOICES` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `PROGRAM_FEES` | Yes | No | Programs | Deferred to a future Programs context ticket |
| `PROGRAM_OVERBOOKING` | Yes | No | Cash Register | Shipped as a case now: `Privilege::ProgramOverbooking` |
| `PROGRAM_PARTICIPANT_DATA` | Yes | No | Programs | Deferred to a future Programs context ticket |
| `PROGRAM_PARTICIPANT_ROLLOVER` | Yes | No | Programs | Deferred to a future Programs context ticket |
| `PROGRAM_PAYMENT_INVOICES` | Yes | Yes | Programs | Deferred to a future Programs context ticket |
| `PROGRAM_PAYROLL` | Yes | No | Programs | Deferred to a future Programs context ticket |
| `PROGRAM_REPORTS` | Yes | Yes | Reports | Deferred to a future Reports context ticket |
| `PROGRAM_ROLLOVER` | Yes | No | Programs | Deferred to a future Programs context ticket |
| `PROGRAM_TRANSFER` | Yes | Yes | Programs | Deferred to a future Programs context ticket |
| `RECALL_ANY_TAB` | Yes | No | Cash Register | Shipped as a case now: `Privilege::RecallAnyTab` |
| `RECALL_LOCATION_TABS` | Yes | No | Cash Register | Shipped as a case now: `Privilege::RecallLocationTabs` |
| `REFUND_REPORTS` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `REFUND_TRANSFER_REASONS` | Yes | No | Customization | Deferred to a future Customization context ticket |
| `REMOVE_ADMINS` | Yes | Yes | Users | Shipped as a case now: `Privilege::RemoveAdmins` |
| `REMOVE_USERS` | Yes | Yes | Users | Shipped as a case now: `Privilege::RemoveUsers` |
| `RENTAL_EFT_INVOICES` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `RENTAL_PAYMENT_INVOICES` | Yes | Yes | Facilities | Deferred to a future Facilities context ticket |
| `SAVE_TAB` | Yes | No | Cash Register | Shipped as a case now: `Privilege::SaveTab` |
| `SEARCH_ALL_TRANSACTIONS` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `SEARCH_LOCATION_TRANSACTIONS` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `SEARCH_OWN_TRANSACTIONS` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `SELL_INVENTORY` | Yes | Yes | Cash Register | Shipped as a case now: `Privilege::SellInventory` |
| `SELL_MEMBERSHIPS` | Yes | Yes | Cash Register | Shipped as a case now: `Privilege::SellMemberships` |
| `SELL_PROGRAMS` | Yes | Yes | Cash Register | Shipped as a case now: `Privilege::SellPrograms` |
| `SELL_RENTALS` | Yes | Yes | Cash Register | Shipped as a case now: `Privilege::SellRentals` |
| `SELL_RESERVATIONS` | Yes | Yes | Cash Register | Shipped as a case now: `Privilege::SellReservations` |
| `SEND_PASSWORD_RESET_EMAIL` | Yes | No | Internet | Deferred to a future Internet context ticket |
| `SHOW_CREDIT_CARD_INFO` | Yes | No | Accounting | Deferred to a future Accounting context ticket |
| `TRANSACTION_REPORTS` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `TWITTER` | Yes | Yes | Communications | Dropped: Legacy Twitter posting integration for the defunct ParkPro2Flex Communications module; the platform (now X) discontinued the free API tier this integration used and no successor screen is planned. |
| `USER_REPORTS` | Yes | Yes | Reports | Deferred to a future Reports context ticket |
| `USER_SCHOLARSHIP_REPORTS` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `USER_TAX_REPORTS` | Yes | No | Reports | Deferred to a future Reports context ticket |
| `VIEW_ADMINS` | Yes | Yes | Users | Shipped as a case now: `Privilege::ViewAdmins` |
| `VIEW_COMMUNICATION_SETTINGS` | Yes | Yes | Communications | Deferred to a future Communications context ticket |
| `VIEW_EQUIPMENT` | Yes | Yes | Inventory | Shipped as a case now: `Privilege::ViewEquipment` |
| `VIEW_FACILITIES` | Yes | Yes | Facilities | Deferred to a future Facilities context ticket |
| `VIEW_INVENTORY` | Yes | Yes | Inventory | Shipped as a case now: `Privilege::ViewInventory` |
| `VIEW_LOCATIONS` | Yes | Yes | Facilities | Deferred to a future Facilities context ticket |
| `VIEW_MEMBERSHIPS` | Yes | Yes | Memberships | Deferred to a future Memberships context ticket |
| `VIEW_MEMBERSHIP_CARDS` | Yes | Yes | Memberships | Deferred to a future Memberships context ticket |
| `VIEW_MEMBERSHIP_LOGGER` | Yes | Yes | Memberships | Deferred to a future Memberships context ticket |
| `VIEW_MEMBERSHIP_TYPES` | Yes | Yes | Memberships | Deferred to a future Memberships context ticket |
| `VIEW_POS_TRANSACTIONS` | Yes | Yes | Cash Register | Shipped as a case now: `Privilege::ViewPosTransactions` |
| `VIEW_PROGRAMS` | Yes | Yes | Programs | Deferred to a future Programs context ticket |
| `VIEW_PROGRAM_TYPES` | Yes | Yes | Programs | Deferred to a future Programs context ticket |
| `VIEW_REFUNDS` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `VIEW_USERS` | Yes | Yes | Users | Shipped as a case now: `Privilege::ViewUsers` |
| `VIEW_USER_GROUPS` | Yes | Yes | Users | Shipped as a case now: `Privilege::ViewUserGroups` |
| `VOID_ALL` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `VOID_SAME_DAY` | Yes | Yes | Accounting | Deferred to a future Accounting context ticket |
| `WAITLIST_PROGRAM_BEFORE_FULL` | Yes | No | Cash Register | Shipped as a case now: `Privilege::WaitlistProgramBeforeFull` |
## Notes on dispositions

- **Dropped (2):** `FACEBOOK` and `TWITTER` — legacy social-media
  posting integrations for the defunct ParkPro2Flex Communications
  module. `FACEBOOK`'s wall-posting API style and `TWITTER`'s free
  posting-API tier are both gone from the respective platforms; no
  successor screen is planned, so these are dropped rather than
  deferred.
- **Deferred (118):** every other non-shipped legacy name, grouped by
  the future bounded context named in the epic (`Programs`,
  `Facilities`, `Memberships`, `Reports`, `Accounting`,
  `Communications`, `Scholarships`, `Customization`, `Internet`,
  `Fee Types`). Each such context's own ticket adds its privileges as
  new `Privilege` cases when it ships the capability they govern —
  adding a privilege is a one-file, two-edit change (see the
  `Privilege` class docblock), so deferring costs nothing here.
- **Net-new (not a legacy row, so not in the table above):**
  `Privilege::ViewPrivilegeAudit` (`VIEW_PRIVILEGE_AUDIT`). Verified
  against both reference copies that neither agency has any privilege
  matching `LOG`, `AUDIT`, `SECURIT` or `HISTOR` beyond
  `VIEW_MEMBERSHIP_LOGGER` (a membership feature, not an audit trail —
  consistent with `admin_log` being empty in both databases), so the
  audit-review privilege LRA-273 needs has no legacy precedent to
  reconcile against.
