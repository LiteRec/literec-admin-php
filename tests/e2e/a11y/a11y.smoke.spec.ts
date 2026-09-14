import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { test, expect } from '../support/fixtures';
import { ANON_STATE } from '../support/auth';
import { ANCHORS } from '../support/anchors';

/**
 * S12 (LRA-174): accessibility smoke. Runs axe across a representative page
 * sample and fails on NEW serious/critical WCAG violations, while baselining
 * the project's known, separately-tracked accessibility debt so the suite is
 * green on the current system.
 *
 * Baselined rules (accepted, tracked elsewhere):
 *  - aria-required-children — the WAI-ARIA menubar navigation pattern (also
 *    suppressed for SonarCloud Web:S6842/S6819 in sonar-project.properties).
 *  - color-contrast, scrollable-region-focusable — open findings in the
 *    project's manual WCAG audits.
 */
const SERIOUS_IMPACTS = ['critical', 'serious'];
const BASELINE_RULES = [
  'aria-required-children',
  'color-contrast',
  'scrollable-region-focusable',
];

async function newSeriousViolations(page: Page): Promise<string[]> {
  const results = await new AxeBuilder({ page }).disableRules(BASELINE_RULES).analyze();
  return results.violations
    .filter((violation) => SERIOUS_IMPACTS.includes(violation.impact ?? ''))
    .map((violation) => violation.id);
}

const AUTHENTICATED_PAGES = [
  { name: 'dashboard', url: '/dashboard' },
  { name: 'users directory', url: '/admin/users' },
  { name: 'inventory list', url: '/admin/inventory' },
  { name: 'inventory reports', url: '/admin/inventory/reports' },
  { name: 'cash register — full', url: '/cash-register' },
  { name: 'cash register — quick', url: '/cash-register/quick' },
  // LRA-194: coming-soon placeholder stub, restyled onto Organic.
  { name: 'placeholder page', url: '/cash-register/pos-transactions' },
];

test.describe('accessibility smoke @a11y', () => {
  for (const target of AUTHENTICATED_PAGES) {
    test(`${target.name} has no new serious or critical axe violations`, async ({ page }) => {
      await page.goto(target.url);

      expect(await newSeriousViolations(page)).toEqual([]);
    });
  }

  test('member detail has no new serious or critical axe violations', async ({ page }) => {
    await page.goto('/admin/users');
    await page.locator('#filter-email').fill(ANCHORS.members.alice.email);
    await page.getByRole('link', { name: ANCHORS.members.alice.name }).click();
    await expect(page.getByTestId('member-header')).toBeVisible();

    expect(await newSeriousViolations(page)).toEqual([]);
  });
});

test.describe('accessibility smoke — dark theme @a11y', () => {
  // LRA-195: Organic dark theme tokens. emulateMedia exercises the same
  // pre-paint bootstrap (base.html.twig reads prefers-color-scheme when no
  // stored choice exists) as an OS-level dark preference, rather than the
  // header toggle, so the same helper also covers the anonymous login page
  // below, which has no toggle control.
  for (const target of AUTHENTICATED_PAGES) {
    test(`${target.name} has no new serious or critical axe violations in the dark theme`, async ({ page }) => {
      await page.emulateMedia({ colorScheme: 'dark' });
      await page.goto(target.url);
      await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

      expect(await newSeriousViolations(page)).toEqual([]);
    });
  }

  test('member detail has no new serious or critical axe violations in the dark theme', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/admin/users');
    await page.locator('#filter-email').fill(ANCHORS.members.alice.email);
    await page.getByRole('link', { name: ANCHORS.members.alice.name }).click();
    await expect(page.getByTestId('member-header')).toBeVisible();

    expect(await newSeriousViolations(page)).toEqual([]);
  });
});

test.describe('accessibility smoke — component library (dev only) @a11y', () => {
  // LRA-185: the Organic lr-* component layer restyle. Dev/test-only page
  // (see DevComponentsController) rendering every restyled component so this
  // covers the new pill controls, tags, table, and dialog treatment in both
  // themes via the header's existing theme toggle.
  test('component library has no new serious or critical axe violations in the light theme', async ({ page }) => {
    await page.goto('/_dev/components');

    expect(await newSeriousViolations(page)).toEqual([]);
  });

  test('component library has no new serious or critical axe violations in the dark theme', async ({ page }) => {
    await page.goto('/_dev/components');
    await page.getByTestId('theme-toggle').click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

    expect(await newSeriousViolations(page)).toEqual([]);
  });
});

test.describe('accessibility smoke — anonymous @a11y', () => {
  test.use({ storageState: ANON_STATE });

  test('login page has no new serious or critical axe violations', async ({ page }) => {
    await page.goto('/login');

    expect(await newSeriousViolations(page)).toEqual([]);
  });

  test('login page has no new serious or critical axe violations in the dark theme', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/login');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

    expect(await newSeriousViolations(page)).toEqual([]);
  });
});
