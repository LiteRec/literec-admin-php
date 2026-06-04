import path from 'node:path';
import type { Page } from '@playwright/test';

/**
 * Shared authentication helpers and storage-state paths for the E2E suite.
 *
 * The setup project (tests/e2e/setup/auth.setup.ts) logs in once per role and
 * persists the browser state to these files; suites reuse them via
 * `storageState` instead of logging in per test. The auth suite (S1, LRA-163)
 * is the exception: it drives the login UI directly and runs anonymous.
 */
export const AUTH_DIR = path.join(process.cwd(), 'playwright', '.auth');
export const ADMIN_STATE = path.join(AUTH_DIR, 'admin.json');
export const MEMBER_STATE = path.join(AUTH_DIR, 'member.json');

/** An empty storage state, for suites that must run unauthenticated. */
export const ANON_STATE = { cookies: [], origins: [] };

/**
 * Shared login value for every seeded principal. This is the well-known,
 * non-secret password set by UsersFixtures for the `test` fixtures group, not a
 * real credential; override it with E2E_PASSWORD when running against an
 * instance seeded differently.
 */
const seededLogin = process.env.E2E_PASSWORD ?? 'fixture-password-1234';

/** Seeded principals from the `test` fixtures group (UsersFixtures). */
export const CREDENTIALS = {
  admin: { username: 'admin', password: seededLogin },
  member: { username: 'member-1', password: seededLogin },
} as const;

/** Logs in through the real login form and waits for the dashboard. */
export async function login(page: Page, username: string, password: string): Promise<void> {
  await page.goto('/login');
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.getByRole('button', { name: /log\s*in/i }).click();
  await page.waitForURL('**/dashboard');
}

/** Asserts that visiting a protected path while anonymous redirects to login. */
export async function expectRedirectToLogin(page: Page, protectedPath: string): Promise<void> {
  await page.goto(protectedPath);
  await page.waitForURL('**/login**');
}
