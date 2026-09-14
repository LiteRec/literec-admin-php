<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * Everything the Full register screen renders: the payer, the household's
 * participant options, the program search results and their add-on options,
 * the sale (cart) lines, and the totals. Presentation-only sample data — a
 * real Transactions context is out of scope for this epic, so nothing here is
 * persisted.
 */
final readonly class CashRegisterData
{
    /**
     * @param list<ParticipantOption> $participants
     * @param list<ProgramResult> $programResults
     * @param list<AddOnOption> $addOnOptions
     * @param list<CartLine> $cart
     */
    public function __construct(
        public RegisterPayer $payer,
        public array $participants,
        public array $programResults,
        public array $addOnOptions,
        public array $cart,
        public SaleTotals $totals,
    ) {
    }

    public function selectedProgram(): ?ProgramResult
    {
        foreach ($this->programResults as $result) {
            if ($result->selected) {
                return $result;
            }
        }

        return null;
    }

    /**
     * The "Add to sale" button label before Alpine hydrates and takes over
     * recomputing it client-side as add-on pills are toggled. Mirrors the
     * template's Alpine calculation so the server-rendered and hydrated
     * states start in agreement.
     */
    public function addToSaleLabel(): string
    {
        $selectedProgram = $this->selectedProgram();
        $cents = $selectedProgram instanceof ProgramResult ? $selectedProgram->priceCents : 0;
        foreach ($this->addOnOptions as $option) {
            if ($option->checked) {
                $cents += $option->priceCents;
            }
        }

        return sprintf('Add to sale · $%s', number_format($cents / 100, 2));
    }
}
