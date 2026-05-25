<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Closed set of categories a Catalog Listing can belong to.
 *
 * Downstream contexts (Inventory, Programs, Memberships, Rentals, future
 * GiftCards) react differently to a sold line based on this enum, so the
 * cases are stable contract surface — never repurpose a value.
 */
enum ListingKind: string
{
    case Inventory = 'INVENTORY';
    case Program = 'PROGRAM';
    case Membership = 'MEMBERSHIP';
    case Rental = 'RENTAL';
    case GiftCard = 'GIFT_CARD';
}
