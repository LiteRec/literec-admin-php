/**
 * Anchor data (LRA-178): the immutable seeded rows the E2E suite reads. Humans
 * and tests agree NOT to mutate these, so the suite passes when run repeatedly
 * against the same persistent database with no intervening reset — the mutating
 * flows all create per-run-unique rows or assert deltas instead of touching an
 * anchor. Documented in tests/e2e/README.md → "Anchor data".
 *
 * The seeded login users (`admin`, `member-1`) are the auth anchors and live in
 * support/auth.ts; everything addressed by stable read identifiers lives here.
 */
export const ANCHORS = {
  members: {
    // Curated household members, reached by their unique email / last name.
    alice: {
      name: 'Alice Smith',
      lastName: 'Smith',
      email: 'alice.smith@example.com',
      residency: 'Resident',
    },
    frank: {
      name: 'Frank Miller',
      lastName: 'Miller',
    },
  },
  inventory: {
    // A seeded facility code (stock + purchase-order flows target it).
    facility: 'FAC-A',
    // A seeded item group that already has member items, shown in the table.
    group: 'Top Sellers Q1',
    // Seeded items ITEM-0001..ITEM-0092 ("Test Item NNNN").
    items: {
      first: { code: 'ITEM-0001', name: 'Test Item 0001' },
      second: { code: 'ITEM-0002', name: 'Test Item 0002' },
      third: { code: 'ITEM-0003', name: 'Test Item 0003' },
      seventh: { code: 'ITEM-0007', name: 'Test Item 0007' },
      // A stable code scope + its bounds, used to assert a deterministic sort
      // regardless of per-run-created (`E2E-`) items.
      sortScope: 'ITEM-009',
      sortLowest: 'ITEM-0090',
      sortHighest: 'ITEM-0092',
    },
  },
} as const;
