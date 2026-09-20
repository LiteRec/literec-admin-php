<?php

declare(strict_types=1);

namespace App\Administration\Domain;

/**
 * The folder a {@see Privilege} is filed under on the permission screen.
 *
 * Distinct from a bounded-context name: `Users` here names the legacy
 * permission-screen folder for household/member record administration
 * (a Households context capability), matching how the source catalogue
 * grouped it, not the `App\Users` login-credentials context. Every case
 * ships with at least one {@see Privilege} today; a context that needs
 * its own folder later (Programs, Facilities, Memberships, ...) adds a
 * case here in that context's own ticket.
 */
enum PrivilegeGroup: string
{
    case Administration = 'ADMINISTRATION';
    case Users = 'USERS';
    case Inventory = 'INVENTORY';
    case CashRegister = 'CASH_REGISTER';

    public function displayName(): string
    {
        return match ($this) {
            self::Administration => 'Administration',
            self::Users => 'Users',
            self::Inventory => 'Inventory',
            self::CashRegister => 'Cash Register',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Administration => 0,
            self::Users => 1,
            self::Inventory => 2,
            self::CashRegister => 3,
        };
    }
}
