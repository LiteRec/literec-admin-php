<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

use DateTimeImmutable;

/**
 * One row in the Recent Transactions feed. Money is pre-formatted in
 * the service so the template never touches locale APIs.
 */
final readonly class TransactionRow
{
    public function __construct(
        public DateTimeImmutable $at,
        public string $userName,
        public string $amountFormatted,
        public string $method,
        public TransactionStatus $status,
    ) {
    }
}
