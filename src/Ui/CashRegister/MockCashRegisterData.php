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
            participants: [
                new ParticipantOption('Baby Bocker', 'M-12548', true),
                new ParticipantOption('Mike Bocker Jr.', 'M-12549'),
                new ParticipantOption('Lily Bocker', 'M-12550'),
            ],
            programResults: [
                new ProgramResult(
                    code: '413-11011D',
                    name: 'Advanced Tap Dancing',
                    schedule: 'Tue 6:30–7:30 PM · Studio B',
                    spots: '4 spots left',
                    price: '$145.00',
                    priceCents: 14_500,
                    selected: true,
                ),
                new ProgramResult(
                    code: '413-11014D',
                    name: 'Beginner Tap Dancing',
                    schedule: 'Thu 5:00–6:00 PM · Studio B',
                    spots: '9 spots left',
                    price: '$120.00',
                    priceCents: 12_000,
                ),
                new ProgramResult(
                    code: '413-11020S',
                    name: 'Youth Soccer — Fall League',
                    schedule: 'Sat 9:00–10:00 AM · Field 2',
                    spots: 'Waitlist',
                    price: '$95.00',
                    priceCents: 9_500,
                ),
            ],
            addOnOptions: [
                new AddOnOption('Costume — Tue 6:30', '$48.00', 4_800, true),
                new AddOnOption('Soccer Uniform', '$8.50', 850),
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
        );
    }

    public function buildQuickSale(): QuickSaleData
    {
        return new QuickSaleData(
            categories: ['All', 'Day Passes', 'Concessions', 'Equipment', 'Guest Fees'],
            tiles: [
                new QuickSaleTile(
                    id: 'adult-day-pass',
                    name: 'Adult Day Pass',
                    category: 'Day Passes',
                    price: '$8.00',
                    unitPriceCents: 800,
                    icon: 'ticket',
                    tint: TileTint::Accent,
                ),
                new QuickSaleTile(
                    id: 'youth-day-pass',
                    name: 'Youth Day Pass',
                    category: 'Day Passes',
                    price: '$5.00',
                    unitPriceCents: 500,
                    icon: 'ticket',
                    tint: TileTint::Accent,
                ),
                new QuickSaleTile(
                    id: 'senior-day-pass',
                    name: 'Senior Day Pass',
                    category: 'Day Passes',
                    price: '$4.00',
                    unitPriceCents: 400,
                    icon: 'ticket',
                    tint: TileTint::Accent,
                ),
                new QuickSaleTile(
                    id: 'punch-card-10-visit',
                    name: '10-Visit Punch Card',
                    category: 'Day Passes',
                    price: '$70.00',
                    unitPriceCents: 7000,
                    icon: 'ticket',
                    tint: TileTint::Accent,
                ),
                new QuickSaleTile(
                    id: 'guest-fee',
                    name: 'Guest Fee',
                    category: 'Guest Fees',
                    price: '$10.00',
                    unitPriceCents: 1000,
                    icon: 'user',
                    tint: TileTint::Sage,
                ),
                new QuickSaleTile(
                    id: 'locker-rental',
                    name: 'Locker Rental',
                    category: 'Equipment',
                    price: '$2.00',
                    unitPriceCents: 200,
                    icon: 'key',
                    tint: TileTint::Neutral,
                ),
                new QuickSaleTile(
                    id: 'towel-rental',
                    name: 'Towel Rental',
                    category: 'Equipment',
                    price: '$3.00',
                    unitPriceCents: 300,
                    icon: 'tag',
                    tint: TileTint::Neutral,
                ),
                new QuickSaleTile(
                    id: 'goggles',
                    name: 'Goggles',
                    category: 'Equipment',
                    price: '$12.00',
                    unitPriceCents: 1200,
                    icon: 'tag',
                    tint: TileTint::Neutral,
                ),
                new QuickSaleTile(
                    id: 'swim-cap',
                    name: 'Swim Cap',
                    category: 'Equipment',
                    price: '$6.00',
                    unitPriceCents: 600,
                    icon: 'tag',
                    tint: TileTint::Neutral,
                ),
                new QuickSaleTile(
                    id: 'bottled-water',
                    name: 'Bottled Water',
                    category: 'Concessions',
                    price: '$1.50',
                    unitPriceCents: 150,
                    icon: 'money',
                    tint: TileTint::Sage,
                ),
                new QuickSaleTile(
                    id: 'granola-bar',
                    name: 'Granola Bar',
                    category: 'Concessions',
                    price: '$2.00',
                    unitPriceCents: 200,
                    icon: 'money',
                    tint: TileTint::Sage,
                ),
                new QuickSaleTile(
                    id: 'sports-drink',
                    name: 'Sports Drink',
                    category: 'Concessions',
                    price: '$2.50',
                    unitPriceCents: 250,
                    icon: 'money',
                    tint: TileTint::Sage,
                ),
            ],
            sale: [
                new QuickSaleLine('adult-day-pass', 2),
                new QuickSaleLine('bottled-water', 2),
                new QuickSaleLine('locker-rental', 1),
                new QuickSaleLine('towel-rental', 1),
            ],
            tenders: [
                new TenderOption('cash', 'Cash', 'money'),
                new TenderOption('card', 'Card', 'card'),
                new TenderOption('gift-card', 'Gift card', 'gift'),
            ],
            taxRateBasisPoints: 700,
            subtotal: '$24.00',
            taxLabel: 'Tax (7%)',
            tax: '$1.68',
            total: '$25.68',
        );
    }
}
