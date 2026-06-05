import { expect, type Page } from '@playwright/test';

/**
 * Resolves a seeded inventory item's UUID from the admin inventory list by
 * searching for its name. Shared by the items, stock and purchase-order suites
 * (LRA-178). Asserts the resolved row matches the term so it fails fast if
 * server-side filtering ever regresses.
 */
export async function itemIdFromSearch(page: Page, term: string): Promise<string> {
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
