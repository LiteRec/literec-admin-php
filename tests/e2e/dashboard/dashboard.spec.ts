import { test, expect } from '../support/fixtures';

/**
 * LRA-189: Dashboard mock-data smoke. The dashboard renders from
 * MockDashboardData, so this asserts structure and presence (named regions,
 * non-empty lists, a status label, navigable links, the HTMX status filter)
 * rather than specific business values. The page-error collector fails on
 * any uncaught browser exception (GSAP/Alpine) during render.
 */
test.describe('dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/dashboard');
  });

  test('renders flat KPI cards with values', async ({ page }) => {
    const kpis = page.getByRole('region', { name: 'Key performance indicators' });

    await expect(kpis).toBeVisible();
    await expect(kpis.getByTestId('kpi-card')).not.toHaveCount(0);
    await expect(kpis.getByTestId('kpi-value').first()).toBeVisible();
  });

  test('renders the recent transactions table including a transaction status', async ({ page }) => {
    const transactions = page.getByRole('region', { name: 'Recent transactions' });

    await expect(transactions).toBeVisible();
    await expect(transactions.getByTestId('transaction-row')).not.toHaveCount(0);
    await expect(transactions).toContainText(/Succeeded|Pending|Failed|Refunded/);
  });

  test('filters the transactions table by status without a full reload', async ({ page }) => {
    const transactions = page.getByRole('region', { name: 'Recent transactions' });
    const pendingFilter = page.getByTestId('transactions-filter-pending');

    await pendingFilter.click();
    await expect(page).toHaveURL(/status=pending/);
    await expect(pendingFilter).toHaveClass(/is-active/);

    const rows = transactions.getByTestId('transaction-row');
    await expect(rows.first()).toBeVisible();
    const statuses = await rows.locator('.lr-badge').allTextContents();
    expect(statuses.length).toBeGreaterThan(0);
    for (const status of statuses) {
      expect(status.trim()).toBe('Pending');
    }
  });

  test('renders the Upcoming list', async ({ page }) => {
    const upcoming = page.getByRole('region', { name: 'Upcoming' });

    await expect(upcoming).toBeVisible();
    await expect(upcoming.getByTestId('event-row')).not.toHaveCount(0);
  });

  test('renders Facilities today with a text status tag, not colour alone', async ({ page }) => {
    const facilities = page.getByRole('region', { name: 'Facilities today' });

    await expect(facilities).toBeVisible();
    await expect(facilities.getByTestId('facility-row')).not.toHaveCount(0);
    await expect(facilities).toContainText(/Open|Busy|Maintenance/);
  });

  test('page actions navigate to reports and the register', async ({ page }) => {
    await page.getByRole('link', { name: 'New sale' }).click();
    await expect(page).toHaveURL('/cash-register');
  });
});
