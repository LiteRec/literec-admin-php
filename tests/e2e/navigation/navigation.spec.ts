import type { Page } from '@playwright/test';
import { test, expect } from '../support/fixtures';
import { MEMBER_STATE } from '../support/auth';

/**
 * S2 (LRA-164): main navigation and app shell. Asserts the seven top-level
 * sections render, links resolve, the active section is marked, a dropdown
 * sub-item navigates, the account menu/logout chrome is present, and the
 * member role sees the same navigation (no role gating exists yet —
 * MainNavigation has no role filter).
 *
 * The page-error collector (imported test) fails any case that triggers an
 * uncaught browser exception during these navigations.
 */

// Top-level sections from App\Ui\Navigation\MainNavigation, in order.
const TOP_LEVEL = [
  { label: 'Cash Register', path: '/cash-register' },
  { label: 'Programs', path: '/programs' },
  { label: 'Users', path: '/admin/users' },
  { label: 'Memberships', path: '/memberships' },
  { label: 'Facilities', path: '/facilities' },
  { label: 'Reports', path: '/reports' },
  { label: 'Communications', path: '/communications' },
];

const mainNav = (page: Page) => page.getByRole('navigation', { name: 'Main navigation' });

test.describe('main navigation', () => {
  test('renders every top-level section with the right destination', async ({ page }) => {
    await page.goto('/dashboard');
    const nav = mainNav(page);

    for (const { label, path } of TOP_LEVEL) {
      const link = nav.getByRole('menuitem', { name: label, exact: true });
      await expect(link).toBeVisible();
      await expect(link).toHaveAttribute('href', path);
    }
  });

  test('each top-level link navigates to its section', async ({ page }) => {
    for (const { label, path } of TOP_LEVEL) {
      await page.goto('/dashboard');
      await mainNav(page).getByRole('menuitem', { name: label, exact: true }).click();
      await expect(page).toHaveURL(path);
    }
  });

  test('marks the current section active', async ({ page }) => {
    await page.goto('/admin/users');

    await expect(
      mainNav(page).getByRole('menuitem', { name: 'Users', exact: true }),
    ).toHaveClass(/bg-litrec-primary/);
  });

  test('a dropdown sub-item navigates to its destination', async ({ page }) => {
    await page.goto('/dashboard');
    const nav = mainNav(page);

    await nav.getByRole('button', { name: 'Toggle Cash Register menu' }).click();
    await nav.getByRole('menuitem', { name: 'Inventory', exact: true }).click();

    await expect(page).toHaveURL('/admin/inventory');
  });
});

test.describe('app shell', () => {
  test('exposes the account menu and sign out control', async ({ page }) => {
    await page.goto('/dashboard');

    await page.getByRole('button', { name: /account menu/i }).click();

    await expect(page.getByRole('menuitem', { name: /sign out/i })).toBeVisible();
  });
});

test.describe('member role', () => {
  test.use({ storageState: MEMBER_STATE });

  test('sees the same top-level navigation (no role gating yet)', async ({ page }) => {
    await page.goto('/dashboard');
    const nav = mainNav(page);

    for (const { label } of TOP_LEVEL) {
      await expect(nav.getByRole('menuitem', { name: label, exact: true })).toBeVisible();
    }
  });
});
