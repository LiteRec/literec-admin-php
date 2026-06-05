import type { Page } from '@playwright/test';
import { test, expect } from '../../support/fixtures';
import { readCsvDownload } from '../../support/downloads';

/**
 * S6 (LRA-168): Inventory item management. Read assertions use the seeded
 * `ITEM-0001..0092` / `Test Item NNNN` set. The create/edit flow uses a per-run
 * unique code (prefixed `E2E-`, which sorts before every seeded `ITEM-` code)
 * so descending code sort deterministically surfaces `ITEM-0092`.
 */
const RUN = `${Date.now()}`;
const NEW_CODE = `E2E-${RUN}`;
const NEW_NAME = `E2E Item ${RUN}`;

const firstRowCode = (page: Page) =>
  page.locator('[data-testid^="inventory-row-"]').first().locator('[data-testid^="edit-inventory-item-"]');

async function itemIdFromSearch(page: Page, term: string): Promise<string> {
  // Server-side filtering via the query param is deterministic; the HTMX
  // debounced input filter is exercised by the controller the same way.
  await page.goto(`/admin/inventory?search=${encodeURIComponent(term)}`);
  const row = page.locator('[data-testid^="inventory-row-"]').first();
  await expect(row).toBeVisible();
  const testId = await row.getAttribute('data-testid');
  if (!testId) {
    throw new Error(`inventory row for "${term}" is missing its data-testid`);
  }
  return testId.replace('inventory-row-', '');
}

test.describe('inventory list', () => {
  test('renders the table and filters by search', async ({ page }) => {
    await page.goto('/admin/inventory');
    await expect(page.getByTestId('inventory-table')).toBeVisible();

    await page.goto('/admin/inventory?search=Test%20Item%200007');

    await expect(page.getByRole('button', { name: 'ITEM-0007', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'ITEM-0001', exact: true })).toHaveCount(0);
  });

  test('sorts by code and falls back on an invalid sort', async ({ page }) => {
    // Scope to the ITEM-0090..0092 codes so both sort directions are
    // deterministic regardless of per-run created (E2E-) items.
    await page.goto('/admin/inventory?search=ITEM-009&sort=code');
    await expect(firstRowCode(page)).toHaveText('ITEM-0090');

    await page.goto('/admin/inventory?search=ITEM-009&sort=-code');
    await expect(firstRowCode(page)).toHaveText('ITEM-0092');

    // Invalid sort is rejected at the boundary and falls back to the default.
    await page.goto('/admin/inventory?sort=not-a-sort');
    await expect(page.getByTestId('inventory-table')).toBeVisible();
    await expect(page.locator('[data-testid^="inventory-row-"]')).not.toHaveCount(0);
  });

  test('paginates the full list', async ({ page }) => {
    await page.goto('/admin/inventory');

    await expect(page.getByTestId('pagination-status')).toContainText('Page 1 of');
    await page.getByTestId('pagination-next').click();
    await expect(page.getByTestId('pagination-status')).toContainText('Page 2 of');
  });

  test('archived-only filter excludes active items', async ({ page }) => {
    await page.goto('/admin/inventory?search=Test%20Item%200001');
    await expect(page.getByRole('button', { name: 'ITEM-0001', exact: true })).toBeVisible();

    await page.goto('/admin/inventory?archived=1&search=Test%20Item%200001');

    await expect(page.getByTestId('inventory-table')).toBeVisible();
    await expect(page.getByRole('button', { name: 'ITEM-0001', exact: true })).toHaveCount(0);
  });
});

test.describe('create and edit', () => {
  test('creates an item then edits its name', async ({ page }) => {
    await page.goto('/admin/inventory');
    await page.getByTestId('open-new-inventory-item').click();
    const dialog = page.locator('#inventory-item-create-modal');
    await expect(dialog).toBeVisible();

    await dialog.getByLabel('Name').fill(NEW_NAME);
    await dialog.getByLabel('Code', { exact: true }).fill(NEW_CODE);
    await dialog.getByLabel('Kind').selectOption({ label: 'Inventory' });
    await dialog.getByLabel('POS button color').fill('#336699');
    await dialog.getByLabel('Ledger account').fill('4000');
    await dialog.getByLabel('Base fee (cents)').fill('500');
    await dialog.getByLabel('Reorder threshold (units)').fill('5');
    await dialog.getByTestId('inventory-item-submit').click();
    await expect(dialog).toBeHidden();

    // The modal closes and the create persists; find it via a filtered reload.
    await page.goto(`/admin/inventory?search=${encodeURIComponent(NEW_CODE)}`);
    await expect(page.getByRole('button', { name: NEW_CODE, exact: true })).toBeVisible();

    // Edit the freshly created item.
    await page.getByRole('button', { name: NEW_CODE, exact: true }).click();
    const editDialog = page.locator('#inventory-item-edit-modal');
    await expect(editDialog).toBeVisible();
    await editDialog.getByLabel('Name').fill(`${NEW_NAME} EDITED`);
    await editDialog.getByTestId('inventory-item-submit').click();
    await expect(editDialog).toBeHidden();

    await expect(page.getByText(`${NEW_NAME} EDITED`)).toBeVisible();
  });
});

test.describe('barcode', () => {
  test('renders a printable barcode for an item', async ({ page }) => {
    const itemId = await itemIdFromSearch(page, 'Test Item 0001');

    await page.goto(`/admin/inventory/${itemId}/barcode`);

    await expect(page.getByTestId('barcode-render')).toBeVisible();
    await expect(page.getByTestId('barcode-code')).toContainText('ITEM-0001');
    await expect(page.getByTestId('print-barcode')).toBeVisible();
  });
});

test.describe('history', () => {
  test('renders the history page, loads batches, and exports a CSV', async ({ page }) => {
    const itemId = await itemIdFromSearch(page, 'Test Item 0001');

    await page.getByTestId(`history-link-${itemId}`).click();
    await expect(page).toHaveURL(`/admin/inventory/${itemId}/history`);

    await expect(page.getByTestId('history-item-id')).toContainText(itemId);
    await expect(page.getByTestId('history-on-hand')).toBeVisible();

    // The movements partial renders. Seeded stock is held as FIFO batches, so
    // the movements list is empty until a receive/adjust (covered by S8); the
    // batches partial below carries the seeded read data.
    await expect(page.getByTestId('history-movements')).toBeVisible();

    // Batches partial loads on demand with the seeded FIFO batches.
    await page.getByTestId('history-show-batches').click();
    await expect(
      page.getByTestId('history-batch-panel').locator('[data-testid^="history-batch-row-"]'),
    ).not.toHaveCount(0);

    // CSV export downloads with the documented header row.
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('history-export-csv').click(),
    ]);
    const csv = await readCsvDownload(download);
    expect(csv.header).toEqual([
      'Date',
      'Kind',
      'Reason',
      'Facility',
      'Quantity',
      'BatchReceived',
      'CostPerUnit',
      'OperatorNote',
      'TransactionId',
    ]);
  });
});
