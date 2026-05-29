<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * Everything the Full register screen renders: the payer, the in-progress
 * program selection and its add-on options, the shopping cart, the totals, and
 * the selected facility. Presentation-only sample data — a real Transactions
 * context is out of scope for this epic, so nothing here is persisted.
 */
final readonly class CashRegisterData
{
    /**
     * @param list<AddOnOption> $addOnOptions
     * @param list<CartLine> $cart
     */
    public function __construct(
        public RegisterPayer $payer,
        public string $participantName,
        public string $participantId,
        public ProgramSelection $program,
        public array $addOnOptions,
        public array $cart,
        public SaleTotals $totals,
        public string $facility,
    ) {
    }
}
