import { test, expect } from '../support/fixtures';
import { ANCHORS } from '../support/anchors';

/**
 * S4 (LRA-166): Cash Register mock-data smoke. Both screens render from
 * MockCashRegisterData. LRA-190 rebuilds the Full register onto artboard 1b
 * (payer/participant sand cards, pill tabs, program results with add-on
 * toggles, Sale rail); its add-on toggles recompute the displayed "Add to
 * sale" total client-side. LRA-191 rebuilds the Quick sale screen onto
 * artboard 1c with the same kind of Alpine-driven interactivity (tapping a
 * tile, stepping a line, clearing the receipt, tender selection). Neither
 * screen mutates anything server-side.
 */
test.describe('cash register — full sale', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/cash-register');
  });

  test('renders the payer and participant cards, and the program builder', async ({ page }) => {
    await expect(page.getByText('Payer', { exact: true })).toBeVisible();
    await expect(page.getByText('Participant', { exact: true })).toBeVisible();
    await expect(page.getByRole('radio', { checked: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Programs', exact: true })).toBeVisible();
    await expect(page.getByText('Advanced Tap Dancing')).toBeVisible();
  });

  test('toggling an add-on pill updates the Add to sale total and its pressed state', async ({ page }) => {
    const addToSaleButton = page.getByRole('button', { name: /Add to sale/ });
    const costumeChip = page.getByRole('button', { name: /Costume/ });
    const soccerChip = page.getByRole('button', { name: /Soccer Uniform/ });

    // Pre-checked in MockCashRegisterData — must be pressed before Alpine hydrates.
    await expect(costumeChip).toHaveAttribute('aria-pressed', 'true');
    await expect(soccerChip).toHaveAttribute('aria-pressed', 'false');
    await expect(addToSaleButton).toContainText('$193.00');

    await soccerChip.click();

    await expect(soccerChip).toHaveAttribute('aria-pressed', 'true');
    await expect(addToSaleButton).toContainText('$201.50');
  });

  test('selecting a different participant moves the checkmark to that row', async ({ page }) => {
    const babyPill = page.locator('.lr-pillradio', { hasText: 'Baby Bocker' });
    const juniorPill = page.locator('.lr-pillradio', { hasText: 'Mike Bocker Jr.' });

    await expect(babyPill.locator('input[type="radio"]')).toBeChecked();
    await expect(babyPill.locator('.lr-pillradio-check')).toBeVisible();
    await expect(juniorPill.locator('.lr-pillradio-check')).not.toBeVisible();

    await juniorPill.locator('.lr-pillradio-row').click();

    await expect(juniorPill.locator('input[type="radio"]')).toBeChecked();
    await expect(juniorPill.locator('.lr-pillradio-check')).toBeVisible();
    await expect(babyPill.locator('.lr-pillradio-check')).not.toBeVisible();
  });

  test('renders the sale rail with line items and totals', async ({ page }) => {
    await expect(page.getByText('Sale', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /^Remove / })).not.toHaveCount(0);
    await expect(page.getByText('Subtotal')).toBeVisible();
    await expect(page.getByText('Tax', { exact: true })).toBeVisible();
    await expect(page.getByText('Total', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /Take payment/i })).toBeVisible();
  });
});

test.describe('cash register — quick sale', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/cash-register/quick');
  });

  test('renders the item search, category pills, and quick-sale tiles', async ({ page }) => {
    const categoryPills = page.getByRole('group', { name: 'Filter by category' });

    await expect(page.getByLabel('Scan or search an item')).toBeVisible();
    await expect(page.getByTestId('quick-sale-payer')).toHaveText(/Walk-in/);
    await expect(categoryPills.getByRole('button', { name: 'Day Passes', exact: true })).toBeVisible();
    await expect(page.getByTestId('quick-tile')).not.toHaveCount(0);
  });

  test('renders the seeded receipt and charge action', async ({ page }) => {
    await expect(page.getByRole('complementary', { name: 'Receipt' })).toBeVisible();
    await expect(page.getByRole('button', { name: /^Charge/ })).toBeVisible();
    await expect(page.getByTestId('quick-sale-charge')).toHaveText('Charge $25.68');
  });

  test('tapping a new tile adds a receipt line and shows its quantity badge', async ({ page }) => {
    const tile = page.getByTestId('quick-tile').filter({ hasText: 'Guest Fee' });
    const line = page.getByTestId('receipt-line-guest-fee');

    await expect(line).toBeHidden();

    await tile.click();

    await expect(line).toBeVisible();
    await expect(line.locator('.q')).toHaveText('1');
    await expect(tile.locator('.tile-qty-badge')).toHaveText('1');

    await tile.click();

    await expect(line.locator('.q')).toHaveText('2');
    await expect(tile.locator('.tile-qty-badge')).toHaveText('2');
  });

  test('the stepper updates the quantity and the totals', async ({ page }) => {
    const line = page.getByTestId('receipt-line-adult-day-pass');
    const total = page.locator('.lr-totals .row.total .lr-num');

    await expect(line.locator('.q')).toHaveText('2');
    await expect(total).toHaveText('$25.68');

    await line.getByRole('button', { name: /Increase/ }).click();

    await expect(line.locator('.q')).toHaveText('3');
    await expect(total).toHaveText('$34.24');

    await line.getByRole('button', { name: /Decrease/ }).click();
    await line.getByRole('button', { name: /Decrease/ }).click();

    await expect(line.locator('.q')).toHaveText('1');
    await expect(total).toHaveText('$17.12');
  });

  test('Clear empties the receipt and shows the empty state', async ({ page }) => {
    await page.getByTestId('quick-sale-clear').click();

    await expect(page.getByTestId('quick-sale-empty')).toBeVisible();
    await expect(page.getByText('No items yet')).toBeVisible();
    await expect(page.locator('.lr-totals .row.total .lr-num')).toHaveText('$0.00');
    await expect(page.getByTestId('quick-sale-charge')).toHaveText('Charge $0.00');
  });

  test('tender tiles are radio-like, aria-pressed, and keyboard operable', async ({ page }) => {
    const cash = page.getByTestId('quick-sale-tender-cash');
    const card = page.getByTestId('quick-sale-tender-card');

    await expect(cash).toHaveAttribute('aria-pressed', 'false');
    await expect(card).toHaveAttribute('aria-pressed', 'false');

    await cash.focus();
    await page.keyboard.press('Enter');

    await expect(cash).toHaveAttribute('aria-pressed', 'true');
    await expect(card).toHaveAttribute('aria-pressed', 'false');

    await card.focus();
    await page.keyboard.press(' ');

    await expect(cash).toHaveAttribute('aria-pressed', 'false');
    await expect(card).toHaveAttribute('aria-pressed', 'true');
  });

  test('category pills filter the tile grid', async ({ page }) => {
    const guestFeeTile = page.getByTestId('quick-tile').filter({ hasText: 'Guest Fee' });

    await expect(guestFeeTile).toBeVisible();

    await page
      .getByRole('group', { name: 'Filter by category' })
      .getByRole('button', { name: 'Day Passes', exact: true })
      .click();

    await expect(guestFeeTile).toBeHidden();
    await expect(page.getByTestId('quick-tile').filter({ hasText: 'Adult Day Pass' })).toBeVisible();
  });

  test('the Walk-in pill opens member lookup and shows the selected member', async ({ page }) => {
    await page.getByTestId('quick-sale-payer').click();
    await page.getByTestId('member-lookup-input-lastName').fill(ANCHORS.members.alice.lastName);
    await page
      .locator('[data-testid^="member-lookup-row-"]', { hasText: ANCHORS.members.alice.name })
      .click();

    await expect(page.getByTestId('quick-sale-payer')).toHaveText(ANCHORS.members.alice.name);
  });
});
