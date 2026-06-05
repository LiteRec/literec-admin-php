import { test, expect } from '../../support/fixtures';

/**
 * S9 (LRA-171): Inventory purchase-order lifecycle.
 *
 * The seeded draft purchase orders (1-6) are driven through the full lifecycle
 * via the detail page: draft -> sent -> (receive each line) -> fully_received
 * -> verified. Creating a PO via the UI needs raw vendor/item UUIDs that the UI
 * does not surface (no dropdown), so this suite covers the create *form*
 * validation rather than a successful create, and exercises the lifecycle on a
 * seeded draft PO instead.
 */

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
  test('sends, receives every line, then verifies a draft PO', async ({ page }) => {
    // Pick the first seeded draft purchase order.
    await page.goto('/admin/inventory/purchase-orders?status=draft');
    const detailLink = page.locator('[data-testid^="po-detail-link-"]').first();
    await expect(detailLink).toBeVisible();
    await detailLink.click();

    const status = page.getByTestId('po-status');
    await expect(status).toContainText('draft');

    // Send.
    await page.getByTestId('po-send-button').click();
    await expect(status).toContainText('sent');

    // Receive every open line (each receive form pre-fills the remaining units).
    const openButtons = page.locator('[data-testid^="po-line-receive-open-"]');
    const openPrefix = 'po-line-receive-open-';
    // Count-driven loop with a safety cap well above any seeded PO's line count.
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
