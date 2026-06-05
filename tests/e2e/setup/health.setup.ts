import { test as setup, expect } from '@playwright/test';

/**
 * Readiness gate. Playwright's webServer.url already blocks the run until
 * /health answers, but this makes the contract explicit and fails fast with a
 * clear message if the app is unhealthy.
 */
setup('health endpoint reports ok', async ({ request }) => {
  const response = await request.get('/health');
  expect(response).toBeOK();
  expect(await response.json()).toMatchObject({ status: 'ok' });
});
