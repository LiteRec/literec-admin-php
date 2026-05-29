<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * Builds the mock CashRegisterData consumed by the Full register screen. Every
 * value is hand-picked to look realistic so the layout can be reviewed before a
 * Transactions context exists. Nothing here is persisted; the screen performs
 * no backend mutations.
 */
final readonly class MockCashRegisterData
{
    public function build(): CashRegisterData
    {
        return new CashRegisterData(
            payer: new RegisterPayer(
                name: 'Mike Bocker',
                id: 'U-10293',
                initials: 'MB',
                household: 'Bocker Household',
                email: 'm.bocker@example.org',
                phone: '(317) 555-0142',
                accountBalance: '$0.00',
            ),
            participantName: 'Baby Bocker',
            participantId: '12548',
            program: new ProgramSelection(
                code: '413-11011D',
                name: 'Advanced Tap Dancing',
                schedule: 'Tue 6:30–7:30 PM · Studio B',
                price: '$145.00',
            ),
            addOnOptions: [
                new AddOnOption('Costume — Tue 6:30', '$48.00', true),
                new AddOnOption('Soccer Uniform', '$8.50'),
            ],
            cart: [
                new CartLine('program', 'Advanced Tap — Fall Session', '413-(11011D)', 1, '$148.00'),
                new CartLine('membership', 'Corporate 1-Year Membership', '144-(994)', 1, '$475.00'),
                new CartLine('item', 'Babysitting — 12 visits', '(1026)', 1, '$30.00'),
            ],
            totals: new SaleTotals(
                subtotal: '$653.00',
                discounts: '$0.00',
                tax: '$0.00',
                total: '$653.00',
            ),
            facility: 'Community Center',
        );
    }
}
