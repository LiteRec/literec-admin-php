import type { Page } from '@playwright/test';
import { expect } from './fixtures';

/**
 * Creates a per-run household + primary member via the Users list "New
 * household" dialog and leaves the browser on the newly created member's
 * detail page (the register endpoint responds with an HX-Redirect there).
 * Extracted from tests/e2e/users/users.spec.ts (LRA-212) so mutating specs
 * across suites share one construction path instead of duplicating it —
 * every caller creates its own per-run household so no test ever mutates a
 * seeded anchor fixture.
 */
export async function createHousehold(page: Page, firstName: string, lastName: string): Promise<void> {
  await page.goto('/admin/users');
  await page.getByTestId('open-new-household').click();
  const dialog = page.locator('#register-household-modal');
  await expect(dialog).toBeVisible();

  await dialog.getByLabel('Household name').fill(`${lastName} Household`);
  await dialog.getByLabel('First name').fill(firstName);
  await dialog.getByLabel('Last name').fill(lastName);
  await dialog.getByLabel('Date of birth').fill('1990-01-01');
  await dialog.getByLabel('Gender').selectOption({ label: 'Unspecified' });
  await dialog.getByLabel('Email').fill(`${firstName}.${lastName}@example.com`.toLowerCase());
  await dialog.getByLabel('Phone').fill('+1-555-0199');
  await dialog.getByLabel('Residency status').selectOption({ label: 'Resident' });
  await dialog.getByLabel('Street').fill('1 Test St');
  await dialog.getByLabel('City').fill('Testville');
  await dialog.getByLabel('State / Province').fill('CA');
  await dialog.getByLabel('Postal code').fill('94000');
  await dialog.getByLabel('Country (ISO 3166-1 alpha-2)').fill('US');

  const submit = page.getByTestId('register-household-submit');
  await submit.scrollIntoViewIfNeeded();
  await submit.click();
}
