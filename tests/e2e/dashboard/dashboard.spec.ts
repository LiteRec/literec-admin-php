import { test, expect } from '../support/fixtures';

/**
 * S3 (LRA-165): Dashboard mock-data smoke. The dashboard renders from
 * MockDashboardData, so this asserts structure and presence (named regions,
 * non-empty lists, a status label, navigable quick links) rather than specific
 * business values. The page-error collector fails on any uncaught browser
 * exception (GSAP/Alpine) during render.
 */
test.describe('dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/dashboard');
  });

  test('renders KPI cards with values', async ({ page }) => {
    const kpis = page.getByRole('region', { name: 'Key performance indicators' });

    await expect(kpis).toBeVisible();
    await expect(kpis.getByTestId('kpi-card')).not.toHaveCount(0);
    await expect(kpis.getByTestId('kpi-value').first()).toBeVisible();
  });

  test('renders the recent activity feed including a transaction status', async ({ page }) => {
    const activity = page.getByRole('region', { name: 'Recent Activity' });

    await expect(activity).toBeVisible();
    await expect(activity.getByTestId('activity-row')).not.toHaveCount(0);
    await expect(activity).toContainText(/Succeeded|Pending|Failed|Refunded/);
  });

  test('renders the upcoming events list', async ({ page }) => {
    const events = page.getByRole('region', { name: 'Upcoming Events' });

    await expect(events).toBeVisible();
    await expect(events.getByTestId('event-row')).not.toHaveCount(0);
  });

  test('renders quick actions that navigate', async ({ page }) => {
    const quickActions = page.getByRole('region', { name: 'Quick Actions' });
    const links = quickActions.getByRole('link');

    await expect(quickActions).toBeVisible();
    await expect(links).not.toHaveCount(0);

    await links.first().click();
    await expect(page).toHaveURL('/cash-register');
  });
});
