import type { Page } from '@playwright/test';
import { test, expect } from '../../support/fixtures';
import { readCsvDownload, type ParsedCsv } from '../../support/downloads';

/**
 * S10 (LRA-172): Inventory reports hub. Current stock and low-stock alerts are
 * non-empty in the seed (every item has stock, and odd items keep only 5-9
 * units at FAC-A against reorder thresholds up to 14). The entry log is empty
 * (stock is held as batches with no movement-ledger entries), so it is asserted
 * by its header row. The barcode picker prints selected labels.
 */

/** Clicks a report's "Export CSV" control and returns the parsed download. */
async function exportReportCsv(page: Page, exportTestId: string): Promise<ParsedCsv> {
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.getByTestId(exportTestId).click(),
  ]);
  return readCsvDownload(download);
}

// Reports that render rows from the seed and export a populated CSV.
const POPULATED_REPORTS = [
  {
    name: 'current stock',
    tableId: 'report-current-stock-table',
    rowPrefix: 'report-current-stock-row-',
    exportId: 'report-current-stock-export',
    header: ['Code', 'Name', 'Kind', 'Facility', 'OnHand', 'ReorderThreshold', 'AtOrBelowThreshold'],
  },
  {
    name: 'low stock',
    tableId: 'report-low-stock-table',
    rowPrefix: 'report-low-stock-row-',
    exportId: 'report-low-stock-export',
    header: ['ItemId', 'ListingId', 'Facility', 'OnHand', 'ReorderThreshold', 'Shortfall', 'PrimaryVendorId'],
  },
];

test.describe('inventory reports hub', () => {
  test('renders the report cards', async ({ page }) => {
    await page.goto('/admin/inventory/reports');

    await expect(page.getByTestId('report-card-current-stock')).toBeVisible();
    await expect(page.getByTestId('report-card-entry-log')).toBeVisible();
    await expect(page.getByTestId('report-card-low-stock')).toBeVisible();
    await expect(page.getByTestId('report-card-barcode-batch')).toBeVisible();
  });

  for (const report of POPULATED_REPORTS) {
    test(`${report.name} renders rows and exports a populated CSV`, async ({ page }) => {
      await page.goto('/admin/inventory/reports');
      await expect(page.getByTestId(report.tableId)).toBeVisible();
      expect(await page.locator(`[data-testid^="${report.rowPrefix}"]`).count()).toBeGreaterThan(0);

      const csv = await exportReportCsv(page, report.exportId);

      expect(csv.header).toEqual(report.header);
      expect(csv.rows.length).toBeGreaterThan(0);
    });
  }

  test('entry log exports a CSV with the documented header', async ({ page }) => {
    await page.goto('/admin/inventory/reports');
    await expect(page.getByTestId('report-card-entry-log')).toBeVisible();

    const csv = await exportReportCsv(page, 'report-entry-log-export');

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
