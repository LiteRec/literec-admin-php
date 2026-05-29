<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * The payer shown in the Cash Register's left pane. Presentation-only sample
 * data — a real Transactions context is out of scope for this epic. Values
 * arrive pre-formatted so the template never touches locale APIs.
 */
final readonly class RegisterPayer
{
    public function __construct(
        public string $name,
        public string $id,
        public string $initials,
        public string $household,
        public string $email,
        public string $phone,
        public string $accountBalance,
    ) {
    }
}
