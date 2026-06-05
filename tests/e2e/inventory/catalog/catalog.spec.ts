import { test, expect } from '../../support/fixtures';
import { ANCHORS } from '../../support/anchors';

/**
 * S7 (LRA-169): Inventory item groups, combos and item links.
 *
 * Scope note: the current build exposes the group and combo *create* modals on
 * the inventory action bar, plus the passive Groups column on the item table.
 * Group/combo edit and archive, and the entire item-link UI, are not yet
 * surfaced in the app (the routes exist and are covered by the controllers'
 * PHPUnit functional tests). This suite therefore covers the exposed UI:
 * group create + duplicate validation, the seeded-groups read, and the combo
 * create form (dynamic component rows + boundary validation).
 */
const RUN = `${Date.now()}`;
const SEEDED_GROUP_NAME = ANCHORS.inventory.group;

test.describe('item groups', () => {
  test('creates a group via the modal', async ({ page }) => {
    await page.goto('/admin/inventory');
    await page.getByTestId('open-new-inventory-group').click();
    const dialog = page.locator('#inventory-group-create-modal');
    await expect(dialog).toBeVisible();

    await dialog.getByLabel('Group name').fill(`E2E Group ${RUN}`);
    await dialog.getByLabel('Color').fill('#3366cc');
    await dialog.getByTestId('inventory-item-group-submit').click();

    // The modal only dismisses on the groupSaved event, which the controller
    // emits solely on a successful (HTTP 200) save — so a hidden modal is the
    // create-success signal. A freshly created group is empty, so it has no
    // other UI surface to assert against yet (the Groups column only shows
    // groups that already have member items).
    await expect(dialog).toBeHidden();
  });

  test('rejects a duplicate group name without leaking an exception', async ({ page }) => {
    await page.goto('/admin/inventory');
    await page.getByTestId('open-new-inventory-group').click();
    const dialog = page.locator('#inventory-group-create-modal');
    await expect(dialog).toBeVisible();

    await dialog.getByLabel('Group name').fill(SEEDED_GROUP_NAME);
    await dialog.getByLabel('Color').fill('#3366cc');
    await dialog.getByTestId('inventory-item-group-submit').click();

    // The submission is rejected (the modal stays open) and surfaces a mapped
    // domain error, never a raw exception/stack trace.
    await expect(dialog.getByRole('alert')).toBeVisible();
    await expect(dialog.getByText(/Stack Trace|Exception/i)).toHaveCount(0);
  });

  test('shows seeded groups in the inventory table', async ({ page }) => {
    await page.goto('/admin/inventory');

    await expect(page.getByTestId('inventory-table')).toContainText(SEEDED_GROUP_NAME);
  });
});

test.describe('combos', () => {
  test('combo create form adds component rows dynamically', async ({ page }) => {
    await page.goto('/admin/inventory');
    await page.getByTestId('open-new-inventory-combo').click();
    const dialog = page.locator('#inventory-combo-create-modal');
    await expect(dialog).toBeVisible();

    const rows = dialog.getByTestId('combo-component-row');
    const initialCount = await rows.count();
    await dialog.getByTestId('combo-add-component').click();

    await expect(rows).toHaveCount(initialCount + 1);
  });

  test('combo create rejects an invalid parent listing id', async ({ page }) => {
    await page.goto('/admin/inventory');
    await page.getByTestId('open-new-inventory-combo').click();
    const dialog = page.locator('#inventory-combo-create-modal');
    await expect(dialog).toBeVisible();

    const parentField = dialog.getByLabel('Parent catalog listing id');
    await parentField.fill('not-a-uuid');
    await dialog.getByTestId('inventory-combo-submit').click();

    // Rejected at the boundary, specifically on the parent field: the form
    // re-renders with the invalid parent value preserved (the create modal
    // stays open, never emitting comboSaved) and no raw exception leaks.
    await expect(parentField).toHaveValue('not-a-uuid');
    await expect(dialog).toBeVisible();
    await expect(dialog.getByText(/Stack Trace|Exception/i)).toHaveCount(0);
  });
});
