import { test, expect } from '../support/fixtures';

/**
 * S4 (LRA-166): Cash Register mock-data smoke. Both screens render from
 * MockCashRegisterData; LRA-190 rebuilds the Full register onto artboard 1b
 * (payer/participant sand cards, pill tabs, program results with add-on
 * toggles, Sale rail). Every control is a non-functional placeholder except
 * the add-on toggles, which recompute the displayed "Add to sale" total
 * client-side — no backend mutation happens on either screen.
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

  test('renders the item search and quick-sale tiles', async ({ page }) => {
    await expect(page.getByLabel('Scan barcode or search items')).toBeVisible();
    await expect(page.getByTestId('quick-tile')).not.toHaveCount(0);
  });

  test('renders the current sale rail and charge action', async ({ page }) => {
    await expect(page.getByText('Current Sale')).toBeVisible();
    await expect(page.getByRole('button', { name: /^Charge/ })).toBeVisible();
  });
});
