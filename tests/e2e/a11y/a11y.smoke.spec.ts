import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { test, expect } from '../support/fixtures';
import { ANON_STATE } from '../support/auth';

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
  { name: 'cash register', url: '/cash-register' },
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
    await page.locator('#filter-email').fill('alice.smith@example.com');
    await page.getByRole('link', { name: 'Alice Smith' }).click();
    await expect(page.getByTestId('member-header')).toBeVisible();

    expect(await newSeriousViolations(page)).toEqual([]);
  });
});

test.describe('accessibility smoke — anonymous @a11y', () => {
  test.use({ storageState: ANON_STATE });

  test('login page has no new serious or critical axe violations', async ({ page }) => {
    await page.goto('/login');

    expect(await newSeriousViolations(page)).toEqual([]);
  });
});
