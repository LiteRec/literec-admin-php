import { defineConfig, devices } from '@playwright/test';
import { ADMIN_STATE } from './tests/e2e/support/auth';

/**
 * Master Playwright configuration for the LiteRecAdmin E2E suite (LRA-161).
 *
 * The app is driven over plain HTTP via PHP's built-in server against a seeded
 * `test`-env database (`app_test`), so the suite runs identically on a
 * developer machine and in CI without the FrankenPHP/TLS docker stack. Point
 * `E2E_BASE_URL` at the docker stack (for example https://localhost) to run the
 * suite against that instead; the built-in web server is then not started.
 */
// Treat an unset OR empty/whitespace E2E_BASE_URL as "use the managed server".
const e2eBaseUrl = process.env.E2E_BASE_URL?.trim();
const baseURL = e2eBaseUrl || 'http://127.0.0.1:8000';
const usesManagedServer = !e2eBaseUrl;

// Forwarded explicitly to the managed server: PHP's built-in server reads the
// database connection and environment from these. CI sets DATABASE_URL to its
// postgres service; locally it defaults to the standard dev connection.
const databaseUrl =
  process.env.DATABASE_URL ??
  'postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=17&charset=utf8';

export default defineConfig({
  testDir: './tests/e2e',
  // The suites mutate a shared database, so they run serially to keep seeded
  // state deterministic. Revisit per-suite isolation before raising workers.
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  timeout: 30_000,
  expect: { timeout: 7_000 },
  reporter: process.env.CI
    ? [['github'], ['html', { open: 'never' }], ['list']]
    : [['html', { open: 'never' }], ['list']],
  use: {
    baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: true,
  },
  projects: [
    { name: 'setup', testMatch: '**/*.setup.ts' },
    {
      name: 'chromium',
      testIgnore: '**/*.setup.ts',
      // Most suites run as the seeded admin. Suites that need anonymous or
      // member context override storageState per file (see tests/e2e/README.md).
      use: { ...devices['Desktop Chrome'], storageState: ADMIN_STATE },
      dependencies: ['setup'],
    },
  ],
  webServer: usesManagedServer
    ? {
        // index.php is the router script: PHP's built-in server serves existing
        // files under public/ directly and falls back to Symfony for routes.
        // variables_order=EGPCS surfaces the env below in $_ENV for Dotenv.
        command:
          'php -d variables_order=EGPCS -S 127.0.0.1:8000 -t public public/index.php',
        url: 'http://127.0.0.1:8000/health',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        stdout: 'pipe',
        stderr: 'pipe',
        env: {
          APP_ENV: 'test',
          DATABASE_URL: databaseUrl,
          // Multiple workers so a page request plus its asset/HTMX subrequests
          // do not deadlock the single-threaded built-in server.
          PHP_CLI_SERVER_WORKERS: '4',
        },
      }
    : undefined,
});
