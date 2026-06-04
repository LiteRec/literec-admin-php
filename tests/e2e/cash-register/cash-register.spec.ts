import { test, expect } from '../support/fixtures';

/**
 * S4 (LRA-166): Cash Register mock-data smoke. Both screens render from
 * MockCashRegisterData and every control is a non-functional placeholder (no
 * backend mutation, no real Alpine state), so this asserts structure/presence
 * and relies on the page-error collector to catch any uncaught exception during
 * render — there are no functional interactions to exercise yet.
 */
test.describe('cash register — full sale', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/cash-register');
  });

  test('renders the payer panel and program builder', async ({ page }) => {
    await expect(page.getByText('Payer', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Programs', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /Add to Cart/i })).toBeVisible();
  });

  test('renders the shopping cart with line items', async ({ page }) => {
    await expect(page.getByText('Shopping Cart')).toBeVisible();
    await expect(page.getByRole('button', { name: /^Remove / })).not.toHaveCount(0);
  });

  test('renders the sale totals and complete-sale action', async ({ page }) => {
    await expect(page.getByText('Subtotal')).toBeVisible();
    await expect(page.getByText('Tax', { exact: true })).toBeVisible();
    await expect(page.getByText('Total', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /Complete Sale/i })).toBeVisible();
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
