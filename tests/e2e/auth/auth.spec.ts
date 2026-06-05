import { test, expect } from '../support/fixtures';
import { ANON_STATE, CREDENTIALS, expectRedirectToLogin, login } from '../support/auth';

/**
 * S1 (LRA-163): authentication, logout, session, CSRF, and the global
 * anonymous-to-login redirect contract. Unlike other suites this one drives the
 * login UI directly and runs unauthenticated.
 */
test.use({ storageState: ANON_STATE });

// A representative sample of routes behind the ^/ -> ROLE_USER firewall rule.
const PROTECTED_SAMPLE = ['/dashboard', '/admin/users', '/admin/inventory'];

test.describe('authentication', () => {
  test('login page renders username, password, csrf token and submit', async ({ page }) => {
    await page.goto('/login');

    await expect(page.getByLabel('Username')).toBeVisible();
    await expect(page.getByLabel('Password')).toBeVisible();
    await expect(page.locator('input[name="_csrf_token"]')).toHaveCount(1);
    await expect(page.getByRole('button', { name: /login/i })).toBeVisible();
  });

  test('admin signs in and lands on the dashboard', async ({ page }) => {
    await login(page, CREDENTIALS.admin.username, CREDENTIALS.admin.password);

    await expect(page).toHaveURL('/dashboard');
  });

  test('member signs in and lands on the dashboard', async ({ page }) => {
    await login(page, CREDENTIALS.member.username, CREDENTIALS.member.password);

    await expect(page).toHaveURL('/dashboard');
  });

  test('invalid credentials return to login with an error and no session', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#username').fill(CREDENTIALS.admin.username);
    await page.locator('#password').fill('definitely-the-wrong-password');
    await page.getByRole('button', { name: /login/i }).click();

    await expect(page).toHaveURL(/\/login/);
    await expect(page.getByRole('alert')).toBeVisible();
    // No session was established: a protected route still redirects to login.
    await expectRedirectToLogin(page, '/dashboard');
  });

  test('last username is preserved after a failed attempt', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#username').fill(CREDENTIALS.member.username);
    await page.locator('#password').fill('definitely-the-wrong-password');
    await page.getByRole('button', { name: /login/i }).click();

    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('#username')).toHaveValue(CREDENTIALS.member.username);
  });

  test('an invalid CSRF token is rejected', async ({ page }) => {
    await page.goto('/login');
    await page
      .locator('input[name="_csrf_token"]')
      .evaluate((el) => ((el as HTMLInputElement).value = 'tampered-csrf-token'));
    await page.locator('#username').fill(CREDENTIALS.admin.username);
    await page.locator('#password').fill(CREDENTIALS.admin.password);
    await page.getByRole('button', { name: /login/i }).click();

    await expect(page).toHaveURL(/\/login/);
    await expect(page.getByRole('alert')).toBeVisible();
    await expectRedirectToLogin(page, '/dashboard');
  });

  test('logout clears the session', async ({ page }) => {
    await login(page, CREDENTIALS.admin.username, CREDENTIALS.admin.password);

    // Logout is CSRF-protected, so it must go through the real account-menu
    // form rather than a bare GET /logout.
    await page.getByRole('button', { name: /account menu/i }).click();
    await page.getByRole('menuitem', { name: /sign out/i }).click();
    await page.waitForURL('**/login**');

    await expectRedirectToLogin(page, '/dashboard');
  });
});

test.describe('access control', () => {
  for (const protectedPath of PROTECTED_SAMPLE) {
    test(`anonymous ${protectedPath} redirects to login`, async ({ page }) => {
      await expectRedirectToLogin(page, protectedPath);
    });
  }

  test('health is publicly reachable while anonymous', async ({ request }) => {
    const response = await request.get('/health');

    expect(response).toBeOK();
    expect(await response.json()).toMatchObject({ status: 'ok' });
  });
});
