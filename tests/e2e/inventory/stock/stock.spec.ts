import type { Page } from '@playwright/test';
import { test, expect } from '../../support/fixtures';

/**
 * S8 (LRA-170): Inventory stock operations — receiving stock and physical-count
 * take-inventory. Both change the item's on-hand quantity (receive adds a FIFO
 * batch; a take variance adjusts on-hand), so each operation is verified by the
 * total on-hand shown on the item-history page changing by the expected delta.
 */
async function itemIdFromSearch(page: Page, term: string): Promise<string> {
  await page.goto(`/admin/inventory?search=${encodeURIComponent(term)}`);
  const row = page.locator('[data-testid^="inventory-row-"]').first();
  await expect(row).toBeVisible();
  await expect(row).toContainText(term);
  const testId = await row.getAttribute('data-testid');
  if (!testId) {
    throw new Error(`inventory row for "${term}" is missing its data-testid`);
  }
  return testId.replace('inventory-row-', '');
}

async function onHand(page: Page, itemId: string): Promise<number> {
  await page.goto(`/admin/inventory/${itemId}/history`);
  const text = (await page.getByTestId('history-on-hand').innerText()).trim();
  const value = Number(text);
  if (!Number.isFinite(value)) {
    throw new Error(`on-hand for ${itemId} is not numeric: "${text}"`);
  }
  return value;
}

test.describe('receive stock', () => {
  test('increases on-hand by the received quantity', async ({ page }) => {
    const itemId = await itemIdFromSearch(page, 'Test Item 0001');
    const before = await onHand(page, itemId);

    await page.goto('/admin/inventory?search=Test%20Item%200001');
    await page.getByTestId(`receive-stock-${itemId}`).click();
    const dialog = page.locator('#receive-stock-modal');
    await expect(dialog).toBeVisible();

    await dialog.getByLabel('Facility').selectOption('FAC-A');
    await dialog.getByLabel('Quantity (units)').fill('5');
    await dialog.getByLabel('Cost per unit (cents)').fill('100');
    await dialog.getByTestId('receive-stock-submit').click();
    await expect(dialog).toBeHidden();

    expect(await onHand(page, itemId)).toBe(before + 5);
  });

  test('rejects a non-positive receipt quantity with a mapped error', async ({ page }) => {
    const itemId = await itemIdFromSearch(page, 'Test Item 0002');

    await page.getByTestId(`receive-stock-${itemId}`).click();
    const dialog = page.locator('#receive-stock-modal');
    await expect(dialog).toBeVisible();

    await dialog.getByLabel('Facility').selectOption('FAC-A');
    await dialog.getByLabel('Quantity (units)').fill('0');
    await dialog.getByLabel('Cost per unit (cents)').fill('100');
    await dialog.getByTestId('receive-stock-submit').click();

    // The form re-renders with a visible mapped error summary and no raw
    // exception/stack trace.
    await expect(dialog.getByTestId('form-error')).toBeVisible();
    await expect(dialog.getByText(/Stack Trace|Exception/i)).toHaveCount(0);
  });
});

test.describe('take inventory', () => {
  test('posts a physical-count variance and adjusts on-hand', async ({ page }) => {
    await page.goto('/admin/inventory/take?facilityCode=FAC-A');
    const grid = page.locator('#take-inventory-grid');
    await expect(grid).toBeVisible();

    // Identify the first row's item id.
    const itemId = await grid
      .locator('[data-testid^="take-row-"]')
      .first()
      .locator('input[name$="[itemId]"]')
      .inputValue();
    expect(itemId).not.toBe('');

    // Total on-hand before the count (navigates away, so re-open the grid after).
    const before = await onHand(page, itemId);

    // Re-open the grid and re-locate the same item's row by its id (not a stale
    // index), so the count targets the correct row after the reload.
    await page.goto('/admin/inventory/take?facilityCode=FAC-A');
    const row = grid
      .locator('tr[data-testid^="take-row-"]')
      .filter({ has: page.locator(`input[name$="[itemId]"][value="${itemId}"]`) });
    const index = ((await row.getAttribute('data-testid')) ?? '').replace('take-row-', '');

    const facilityExpected = Number(
      (await page.getByTestId(`row-${index}-expected`).innerText()).trim(),
    );
    expect(Number.isFinite(facilityExpected)).toBe(true);

    // Count one unit above the facility expected → a +1 variance → total +1.
    await page.getByTestId(`row-${index}-actual`).fill(String(facilityExpected + 1));
    await page.getByTestId(`row-${index}-reason`).selectOption('found');
    await page.getByTestId('take-inventory-submit').click();

    await expect(async () => {
      expect(await onHand(page, itemId)).toBe(before + 1);
    }).toPass();
  });
});
