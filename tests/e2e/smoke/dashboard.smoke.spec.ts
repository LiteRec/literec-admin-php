import { test, expect } from '../support/fixtures';

/**
 * Trivial smoke test proving the harness works end to end: seeded DB, managed
 * web server, admin storage state, and the page-error collector. This is the
 * spec the S0 acceptance criteria require to pass headed and headless.
 */
test.describe('smoke', () => {
  test('dashboard loads for an authenticated admin', async ({ page }) => {
    await page.goto('/dashboard');

    await expect(page).toHaveURL('/dashboard');
    await expect(page.getByRole('main')).toBeVisible();
  });
});
