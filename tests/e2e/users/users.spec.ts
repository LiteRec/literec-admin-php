import type { Page } from '@playwright/test';
import { test, expect } from '../support/fixtures';
import { ANCHORS } from '../support/anchors';

/**
 * S5 (LRA-167): Users & Households. Read assertions reach curated seeded
 * members by filtering on their unique email (the directory also contains bulk
 * faker households, so curated members are not on page one). Mutating flows
 * create their own per-run household so they never disturb seeded read fixtures
 * and stay additive on a persistent dev database.
 */

// Unique suffix so created households/emails do not collide across runs.
const RUN = `${Date.now()}`;
const ALICE = ANCHORS.members.alice;
const FRANK = ANCHORS.members.frank;

async function createHousehold(page: Page, firstName: string, lastName: string): Promise<void> {
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

test.describe('directory', () => {
  test('finds a seeded member via the primary search field', async ({ page }) => {
    await page.goto('/admin/users');
    await expect(page.getByTestId('members-table')).toBeVisible();

    await page.locator('#filter-q').fill(FRANK.lastName);

    await expect(page.getByRole('link', { name: FRANK.name })).toBeVisible();
  });

  test('More filters reveals the detailed fields and combines with q (AND)', async ({ page }) => {
    await page.goto('/admin/users');

    await page.getByTestId('more-filters-toggle').click();
    await expect(page.locator('#filter-email')).toBeVisible();

    // Email uniquely isolates Alice, whose common last name ("Smith") would
    // otherwise collide with the bulk faker households on q alone.
    await page.locator('#filter-email').fill(ALICE.email);
    await expect(page.getByRole('link', { name: ALICE.name })).toBeVisible();

    // Narrowing further with q for a different member yields no rows —
    // proves the two mechanisms combine with AND, not OR.
    await page.locator('#filter-q').fill(FRANK.lastName);
    await expect(page.getByRole('link', { name: ALICE.name })).toHaveCount(0);
  });

  test('segment pills show counts and filter the table, updating the URL', async ({ page }) => {
    await page.goto('/admin/users');

    const inactivePill = page.getByTestId('segment-pill-inactive');
    await expect(inactivePill).toBeVisible();
    await expect(inactivePill).toHaveAttribute('aria-pressed', 'false');

    await inactivePill.click();

    await expect(inactivePill).toHaveAttribute('aria-pressed', 'true');
    await expect(page).toHaveURL(/segment=inactive/);
  });

  test('member lookup validates input at the boundary and returns matches', async ({ request }) => {
    const invalid = await request.get('/admin/users/_lookup?pageSize=999');
    expect(invalid.status()).toBe(400);

    const byLastName = await request.get(`/admin/users/_lookup?lastName=${ALICE.lastName}`);
    expect(byLastName.ok()).toBeTruthy();
    expect(await byLastName.text()).toContain(ALICE.name);
  });
});

test.describe('member detail', () => {
  test('shows the profile, address, residency and history cards', async ({ page }) => {
    await page.goto('/admin/users');
    await page.locator('#filter-email').fill(ALICE.email);
    await page.getByRole('link', { name: ALICE.name }).click();

    await expect(page.getByTestId('member-header')).toContainText(ALICE.name);
    await expect(page.getByTestId('card-profile')).toBeVisible();
    await expect(page.getByTestId('card-address')).toBeVisible();
    await expect(page.getByTestId('card-history')).toBeVisible();
    await expect(page.getByTestId('profile-email')).toContainText(ALICE.email);
    // Residency is a sub-card within the Address & Residency card; Alice is a Resident.
    await expect(page.getByTestId('residency-sub-card-body')).toBeVisible();
    await expect(page.getByTestId('residency-status-badge')).toContainText(ALICE.residency);
  });
});

test.describe('create and edit', () => {
  test('creates a household then edits profile, residency and address', async ({ page }) => {
    const lastName = `Editcase${RUN}`;
    await createHousehold(page, 'Quinn', lastName);

    // HX-Redirect lands on the newly created member's detail page.
    await expect(page.getByTestId('member-header')).toContainText(`Quinn ${lastName}`);

    // Edit profile.
    await page.getByTestId('profile-edit').click();
    await page.getByTestId('card-profile').getByLabel('First name').fill('Quincy');
    await page.getByTestId('profile-save').click();
    await expect(page.getByTestId('profile-first-name')).toContainText('Quincy');

    // Edit residency.
    await page.getByTestId('residency-edit').click();
    await page.getByTestId('card-address').getByLabel('Residency status').selectOption({ label: 'Member' });
    await page.getByTestId('residency-save').click();
    await expect(page.getByTestId('residency-status-badge')).toContainText('Member');

    // Edit address.
    await page.getByTestId('address-edit').click();
    await page.getByTestId('card-address').getByLabel('City').fill('Newcity');
    await page.getByTestId('address-save').click();
    await expect(page.getByTestId('address-sub-card-body')).toContainText('Newcity');
  });

  test('adds a member to an existing household', async ({ page }) => {
    const lastName = `Addcase${RUN}`;
    await createHousehold(page, 'Primary', lastName);
    await expect(page.getByTestId('member-header')).toContainText(`Primary ${lastName}`);

    // Open the add-member dialog from the household card.
    await page.getByTestId('add-member').click();
    const dialog = page.locator('#add-member-modal');
    await expect(dialog).toBeVisible();

    await dialog.getByLabel('First name').fill('Sibling');
    await dialog.getByLabel('Last name').fill(lastName);
    await dialog.getByLabel('Date of birth').fill('2001-05-05');
    await dialog.getByLabel('Gender').selectOption({ label: 'Unspecified' });
    await dialog.getByLabel('Email').fill(`sibling.${lastName}@example.com`.toLowerCase());
    await dialog.getByLabel('Phone').fill('+1-555-0198');
    await dialog.getByLabel('Residency status').selectOption({ label: 'Resident' });

    const submit = page.getByTestId('add-member-submit');
    await submit.scrollIntoViewIfNeeded();
    await submit.click();

    // Success redirects to the new member's detail page.
    await expect(page.getByTestId('member-header')).toContainText(`Sibling ${lastName}`);
  });
});

test.describe('member lookup results', () => {
  // LRA-194: the avatar-initial span must be aria-hidden so it does not leak
  // into the result row's accessible name — the name must start with the
  // member's full name, not the initial. Hitting the results fragment
  // endpoint directly (what the dialog's HTMX search swaps in) lets the
  // browser compute the real accessible name without driving the dialog's
  // open/close chrome.
  test('a result row exposes an accessible name starting with the member\'s full name', async ({ page }) => {
    await page.goto(`/admin/users/_lookup?lastName=${ALICE.lastName}`);

    await expect(page.getByRole('button', { name: new RegExp(`^${ALICE.name}`) })).toBeVisible();
  });
});
