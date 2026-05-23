<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * Closed set of statuses a transaction row can display on the dashboard.
 * Backed so it round-trips through serialisation and Twig comparisons
 * without ad-hoc string juggling.
 */
enum TransactionStatus: string
{
    case Succeeded = 'succeeded';
    case Pending = 'pending';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
