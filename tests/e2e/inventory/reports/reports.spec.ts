import { test, expect } from '../../support/fixtures';
import { readCsvDownload } from '../../support/downloads';

/**
 * S10 (LRA-172): Inventory reports hub. Current stock and low-stock alerts are
 * non-empty in the seed (every item has stock, and odd items keep only 5-9
 * units at FAC-A against reorder thresholds up to 14). The entry log is empty
 * (stock is held as batches with no movement-ledger entries), so it is asserted
 * by its header row. The barcode picker prints selected labels.
 */
test.describe('inventory reports hub', () => {
  test('renders the report cards', async ({ page }) => {
    await page.goto('/admin/inventory/reports');

    await expect(page.getByTestId('report-card-current-stock')).toBeVisible();
    await expect(page.getByTestId('report-card-entry-log')).toBeVisible();
    await expect(page.getByTestId('report-card-low-stock')).toBeVisible();
    await expect(page.getByTestId('report-card-barcode-batch')).toBeVisible();
  });

  test('current stock renders rows and exports a populated CSV', async ({ page }) => {
    await page.goto('/admin/inventory/reports');
    await expect(page.getByTestId('report-current-stock-table')).toBeVisible();
    expect(await page.locator('[data-testid^="report-current-stock-row-"]').count()).toBeGreaterThan(0);

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('report-current-stock-export').click(),
    ]);
    const csv = await readCsvDownload(download);

    expect(csv.header).toEqual([
      'Code',
      'Name',
      'Kind',
      'Facility',
      'OnHand',
      'ReorderThreshold',
      'AtOrBelowThreshold',
    ]);
    expect(csv.rows.length).toBeGreaterThan(0);
  });

  test('entry log exports a CSV with the documented header', async ({ page }) => {
    await page.goto('/admin/inventory/reports');
    await expect(page.getByTestId('report-card-entry-log')).toBeVisible();

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('report-entry-log-export').click(),
    ]);
    const csv = await readCsvDownload(download);

    expect(csv.header).toEqual([
      'Date',
      'Code',
      'ItemId',
      'Facility',
      'Kind',
      'Reason',
      'Quantity',
      'CostPerUnit',
      'OperatorNote',
    ]);
  });

  test('low stock lists threshold breaches and exports a populated CSV', async ({ page }) => {
    await page.goto('/admin/inventory/reports');
    // Odd items keep only 5-9 units at FAC-A against reorder thresholds up to 14,
    // so the seed has at-or-below-threshold alerts.
    await expect(page.getByTestId('report-low-stock-table')).toBeVisible();
    expect(await page.locator('[data-testid^="report-low-stock-row-"]').count()).toBeGreaterThan(0);

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('report-low-stock-export').click(),
    ]);
    const csv = await readCsvDownload(download);

    expect(csv.header).toEqual([
      'ItemId',
      'ListingId',
      'Facility',
      'OnHand',
      'ReorderThreshold',
      'Shortfall',
      'PrimaryVendorId',
    ]);
    expect(csv.rows.length).toBeGreaterThan(0);
  });
});

test.describe('barcode batch report', () => {
  test('prints labels for the selected items', async ({ page }) => {
    await page.goto('/admin/inventory/reports/barcodes');
    await expect(page.getByTestId('report-barcode-picker-table')).toBeVisible();

    await page.locator('[data-testid^="report-barcode-pick-"]').first().check();
    await expect(page.getByTestId('report-barcode-selection-count')).toContainText('1');

    // The print form opens the printable label page in a new tab.
    const [printPage] = await Promise.all([
      page.waitForEvent('popup'),
      page.getByTestId('report-barcode-print-submit').click(),
    ]);

    await expect(printPage.getByTestId('report-barcode-label-grid')).toBeVisible();
    await expect(printPage.locator('[data-testid^="report-barcode-label-"]')).not.toHaveCount(0);
  });
});
