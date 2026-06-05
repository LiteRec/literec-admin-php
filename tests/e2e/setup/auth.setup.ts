import { test as setup } from '@playwright/test';
import fs from 'node:fs';
import { ADMIN_STATE, AUTH_DIR, CREDENTIALS, MEMBER_STATE, login } from '../support/auth';

// Created at module load (not inside a setup test) so the directory is
// guaranteed to exist before either role's storageState write, regardless of
// the order Playwright runs the setup tests in. mkdirSync(recursive) is
// idempotent and cheap.
fs.mkdirSync(AUTH_DIR, { recursive: true });

/**
 * Logs in once per role and persists the browser storage state. Downstream
 * suites reuse these states via `storageState` (project default = admin).
 */
setup('authenticate as admin', async ({ page }) => {
  await login(page, CREDENTIALS.admin.username, CREDENTIALS.admin.password);
  await page.context().storageState({ path: ADMIN_STATE });
});

setup('authenticate as member', async ({ page }) => {
  await login(page, CREDENTIALS.member.username, CREDENTIALS.member.password);
  await page.context().storageState({ path: MEMBER_STATE });
});
