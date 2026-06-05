import type { Page } from '@playwright/test';
import { test, expect } from '../../support/fixtures';
import { ANCHORS } from '../../support/anchors';
import { itemIdFromSearch } from '../../support/inventory';

/**
 * S9 (LRA-171): Inventory purchase-order lifecycle.
 *
 * The lifecycle test drives a PO through draft -> sent -> (receive each line)
 * -> fully_received -> verified. To stay repeatable against a persistent
 * database (LRA-178), it creates its OWN draft PO per run rather than consuming
 * one of the seeded draft anchors (1-6) that the list/filter test reads. The
 * create form takes raw vendor/item UUIDs, so the test harvests a real vendor id
 * from a seeded PO's detail page and a real item id from the inventory list.
 */

/** Harvests a real seeded vendor id from the first purchase order's detail. */
async function seededVendorId(page: Page): Promise<string> {
  await page.goto('/admin/inventory/purchase-orders');
  await page.locator('[data-testid^="po-detail-link-"]').first().click();
  const vendorId = (await page.getByTestId('po-detail-vendor-id').innerText()).trim();
  expect(vendorId).not.toBe('');
  return vendorId;
}

/** Creates a fresh draft PO via the form and lands on its detail page. */
async function createDraftPurchaseOrder(page: Page): Promise<void> {
  const vendorId = await seededVendorId(page);
  const itemId = await itemIdFromSearch(page, ANCHORS.inventory.items.third.name);

  await page.goto('/admin/inventory/purchase-orders/new');
  await expect(page.locator('#purchase-order-form')).toBeVisible();

  await page.getByTestId('po-vendor-id').fill(vendorId);
  await page.getByTestId('po-facility-code').fill(ANCHORS.inventory.facility);

  // The form already renders one empty line row; fill it directly. Its fields are
  // reached by their name suffix (same pattern as the take-inventory grid).
  const line = page.getByTestId('po-line-rows').locator('.po-line-row').first();
  await line.locator('input[name$="[itemId]"]').fill(itemId);
  await line.locator('input[name$="[orderedQuantityUnits]"]').fill('3');
  await line.locator('input[name$="[costPerUnitCents]"]').fill('250');
  await page.getByTestId('po-create-submit').click();

  // A successful create redirects to the new PO's detail page (draft).
  await expect(page.getByTestId('po-status')).toContainText('draft');
}

test.describe('purchase order list', () => {
  test('renders the table and filters by status', async ({ page }) => {
    await page.goto('/admin/inventory/purchase-orders');
    await expect(page.getByTestId('purchase-orders-table')).toBeVisible();

    await page.goto('/admin/inventory/purchase-orders?status=draft');
    const statuses = page.locator('[data-testid^="po-status-"]');
    await expect(statuses).not.toHaveCount(0);
    // Every row in the filtered list is a draft.
    const texts = await statuses.allTextContents();
    expect(texts.every((text) => text.includes('draft'))).toBe(true);
  });
});

test.describe('purchase order create form', () => {
  test('rejects an invalid vendor id on the parent field', async ({ page }) => {
    await page.goto('/admin/inventory/purchase-orders/new');
    await expect(page.locator('#purchase-order-form')).toBeVisible();

    const vendorField = page.getByTestId('po-vendor-id');
    await vendorField.fill('not-a-uuid');
    await page.getByTestId('po-facility-code').fill('PRIMARY');
    await page.getByTestId('po-add-line').click();
    await page.getByTestId('po-create-submit').click();

    // Rejected at the boundary: the form re-renders with the invalid vendor id
    // preserved and no raw exception/stack trace leaks.
    await expect(vendorField).toHaveValue('not-a-uuid');
    await expect(page.getByText(/Stack Trace|Exception/i)).toHaveCount(0);
  });
});

test.describe('purchase order lifecycle', () => {
  test('sends, receives every line, then verifies a per-run PO', async ({ page }) => {
    // Build a fresh draft so seeded draft anchors are never consumed.
    await createDraftPurchaseOrder(page);

    const status = page.getByTestId('po-status');
    await expect(status).toContainText('draft');

    // Send.
    await page.getByTestId('po-send-button').click();
    await expect(status).toContainText('sent');

    // Receive every open line (each receive form pre-fills the remaining units).
    const openButtons = page.locator('[data-testid^="po-line-receive-open-"]');
    const openPrefix = 'po-line-receive-open-';
    // Count-driven loop with a safety cap well above any PO's line count.
    for (let guard = 0; guard < 20; guard++) {
      const remaining = await openButtons.count();
      if (remaining === 0) {
        break;
      }
      const openButton = openButtons.first();
      const testId = await openButton.getAttribute('data-testid');
      if (!testId?.startsWith(openPrefix)) {
        throw new Error(`unexpected receive-open testid: ${testId}`);
      }
      const lineId = testId.slice(openPrefix.length);
      await openButton.click();
      await page.getByTestId(`po-line-receive-submit-${lineId}`).click();
      // Synchronize on the HTMX re-render: the received line's open control is gone.
      await expect(openButtons).toHaveCount(remaining - 1);
    }

    await expect(status).toContainText('fully_received');

    // Verify delivery.
    await page.getByTestId('po-verify-button').click();
    await expect(status).toContainText('verified');
    await expect(page.getByTestId('po-no-actions')).toBeVisible();
  });
});
