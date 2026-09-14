<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * Port for the signed-in staff member's display first name, used by the
 * dashboard greeting. Isolates MockDashboardData from Symfony Security so it
 * stays unit-testable with a plain fake instead of the real security stack.
 */
interface CurrentStaffMember
{
    /**
     * Falls back to a generic label when no user is authenticated.
     */
    public function firstName(): string;
}
