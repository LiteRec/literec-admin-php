# LiteRecAdmin E2E suite (Playwright)

End-to-end QA automation for LiteRecAdmin. Tracked under epic **LRA-161**; this
harness is **LRA-162 (S0)** and is consumed by suites S1–S12 (LRA-163–174). The
authoritative scope is the *QA Automation — Master Test Plan* in the project
vault.

## How it runs

The app is driven over plain HTTP through PHP's built-in web server against a
seeded `test`-env database (`app_test`). No FrankenPHP/TLS docker stack is
required, so the suite behaves identically locally and in CI.

- `playwright.config.ts` starts `php -S 127.0.0.1:8000 -t public public/index.php`
  (`APP_ENV=test`, `PHP_CLI_SERVER_WORKERS=4`) and waits for `/health`.
- The `setup` project seeds-state by logging in once per role and saving browser
  storage state under `playwright/.auth/`.
- The `chromium` project defaults to the **admin** storage state.

Point `E2E_BASE_URL` at another instance (for example `https://localhost` for the
docker stack) to skip the managed server and run against that URL.

## Prerequisites

```bash
# 1. A reachable Postgres and the seeded test database
export DATABASE_URL='postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=17&charset=utf8'
composer install
php bin/console tailwind:build
composer db:reset-test          # creates app_test, migrates, loads `test` fixtures

# 2. Node tooling
npm install
npm run e2e:install             # downloads the Chromium browser
```

## Running

```bash
npm run test:e2e          # headless (CI default)
npm run test:e2e:headed   # headed, for human review
npm run test:e2e:ui       # Playwright UI mode
```

## Conventions

- **Auth:** most suites inherit the admin storage state from the project. A suite
  that needs anonymous context sets `test.use({ storageState: ANON_STATE })`; a
  suite that needs the member role sets `test.use({ storageState: MEMBER_STATE })`
  (both exported from `support/auth.ts`).
- **Locators:** prefer `getByRole` / `getByLabel` / `getByText`. Add a
  `data-testid` to a Twig template only when no stable accessible locator exists,
  and record it in the table below.
- **Page errors:** import `test`/`expect` from `support/fixtures.ts` so the
  page-error collector fails any test that triggers an uncaught browser exception.
- **Downloads:** use `readCsvDownload` from `support/downloads.ts` for CSV export
  assertions.
- **Seeded data:** assert against curated personas (`admin`, `member-1`…`member-5`)
  and seeded inventory; treat created rows as additive and name them per-run.

### Added `data-testid` hooks

| testid | Template | Added by |
|--------|----------|----------|
| `kpi-card`, `kpi-value` | `templates/components/_kpi_card.html.twig` | S3 (LRA-165) |
| `activity-row`, `event-row` | `templates/dashboard/index.html.twig` | S3 (LRA-165) |
| `quick-tile` | `templates/cash_register/quick.html.twig` | S4 (LRA-166) |

## Layout

```text
tests/e2e/
  setup/        # health gate + per-role login (storage state)
  support/      # auth, fixtures (page-error collector), downloads helpers
  smoke/        # S0 harness smoke
  …             # one folder per suite, added by S1–S12
```
