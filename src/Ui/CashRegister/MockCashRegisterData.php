<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * Builds the mock data consumed by the Cash Register screens — the Full
 * register (CashRegisterData) and the Quick sale (QuickSaleData). Every value
 * is hand-picked to look realistic so the layouts can be reviewed before a
 * Transactions context exists. Nothing here is persisted; the screens perform
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

    public function buildQuickSale(): QuickSaleData
    {
        return new QuickSaleData(
            categories: ['All', 'Day Passes', 'Concessions', 'Equipment', 'Guest Fees'],
            tiles: [
                new QuickSaleTile('Adult Day Pass', 'Day Passes', '$8.00', 'ticket'),
                new QuickSaleTile('Youth Day Pass', 'Day Passes', '$5.00', 'ticket'),
                new QuickSaleTile('Senior Day Pass', 'Day Passes', '$4.00', 'ticket'),
                new QuickSaleTile('10-Visit Punch Card', 'Day Passes', '$70.00', 'ticket'),
                new QuickSaleTile('Guest Fee', 'Guest Fees', '$10.00', 'user'),
                new QuickSaleTile('Locker Rental', 'Equipment', '$2.00', 'key'),
                new QuickSaleTile('Towel Rental', 'Equipment', '$3.00', 'tag'),
                new QuickSaleTile('Goggles', 'Equipment', '$12.00', 'tag'),
                new QuickSaleTile('Swim Cap', 'Equipment', '$6.00', 'tag'),
                new QuickSaleTile('Bottled Water', 'Concessions', '$1.50', 'money'),
                new QuickSaleTile('Granola Bar', 'Concessions', '$2.00', 'money'),
                new QuickSaleTile('Sports Drink', 'Concessions', '$2.50', 'money'),
            ],
            sale: [
                new QuickSaleLine('Adult Day Pass', 2, '$16.00'),
                new QuickSaleLine('Bottled Water', 2, '$3.00'),
                new QuickSaleLine('Locker Rental', 1, '$2.00'),
                new QuickSaleLine('Towel Rental', 1, '$3.00'),
            ],
            subtotal: '$24.00',
            taxLabel: 'Tax (7%)',
            tax: '$1.68',
            total: '$25.68',
        );
    }
}
