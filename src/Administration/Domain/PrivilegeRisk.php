<?php

declare(strict_types=1);

namespace App\Administration\Domain;

/**
 * How closely successful use of a privilege must be watched.
 *
 * This is a property of the privilege itself, declared once on its
 * {@see PrivilegeDefinition} beside the rest of its screen metadata, so
 * LRA-273's audit trail never carries a second hard-coded list of which
 * privileges are sensitive. {@see Standard} is the default: a privilege
 * is high-risk only by explicit declaration, so a newly added case is
 * never silently promoted into the audit stream.
 */
enum PrivilegeRisk: string
{
    case Standard = 'STANDARD';
    case High = 'HIGH';
}
