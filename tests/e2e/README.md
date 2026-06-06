# LiteRecAdmin E2E suite (Playwright)

End-to-end QA automation for LiteRecAdmin. Tracked under epic **LRA-161**; this
harness is **LRA-162 (S0)** and is consumed by suites S1–S12 (LRA-163–174). The
authoritative scope is the *QA Automation — Master Test Plan* in the project
vault.

## How it runs

The app is driven over plain HTTP through PHP's built-in web server against a
seeded, persistent E2E database. No FrankenPHP/TLS docker stack is required, so
the suite behaves identically locally and in CI.

The E2E lane is its own database, separate from the dev and functional/integration
databases (LRA-176):

| Lane | `APP_ENV` | `DATABASE_URL` base name | Physical database |
|------|-----------|--------------------------|-------------------|
| dev | `dev` | `app` | `app` |
| functional / integration (PHPUnit, rolled back) | `test` | `app` | `app_test` |
| E2E / human testing (persistent) | `test` | `app_e2e` | `app_e2e_test` |

The server runs under `APP_ENV=test`, so the test-env `_test` suffix turns the
`DATABASE_URL` base name `app_e2e` into the physical database `app_e2e_test`. The
lane is selected entirely by the single `DATABASE_URL` env variable: CI sets it to
its postgres service; locally `playwright.config.ts` defaults to the `app_e2e`
connection (override with `DATABASE_URL` to point elsewhere).

- `playwright.config.ts` starts `php -S 127.0.0.1:8000 -t public public/index.php`
  (`APP_ENV=test`, `PHP_CLI_SERVER_WORKERS=4`) and waits for `/health`.
- The `setup` project seeds-state by logging in once per role and saving browser
  storage state under `playwright/.auth/`.
- The `chromium` project defaults to the **admin** storage state.

Point `E2E_BASE_URL` at another instance (for example `https://localhost` for the
docker stack) to skip the managed server and run against that URL.

## Prerequisites

```bash
# 1. A reachable Postgres and the seeded E2E database
composer install
php bin/console tailwind:build
composer db:reset-e2e           # creates app_e2e_test, migrates, loads `test` fixtures

# 2. Node tooling
npm install
npm run e2e:install             # downloads the Chromium browser
```

`composer db:reset-e2e` carries the E2E `DATABASE_URL` itself, so no manual export
is needed; Playwright also defaults to the same `app_e2e` connection. To run the
suite against a different database, export `DATABASE_URL` before invoking it.

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
- **Seeded data:** read only the documented **anchors** (see below); treat created
  rows as additive and name them per-run.

## Anchor data (persistent DB)

The E2E lane is a **persistent** database (it is not dropped between runs), so the
suite must pass when run repeatedly with no intervening reset. The contract that
makes that possible (LRA-178):

- **Reads depend only on anchors** — a small set of immutable seeded rows that
  humans and tests agree not to mutate. They are defined once in
  `support/anchors.ts` (and the seeded login users `admin` / `member-1` in
  `support/auth.ts`); specs import them instead of hard-coding seeded identifiers.
- **Writes stay additive or idempotent** — every mutating flow creates a per-run
  unique row (timestamped name) or asserts a delta (`before + n`), never an
  absolute count, so it never disturbs an anchor or a previous run.

The anchor set (do **not** edit these rows when testing by hand):

| Anchor | Identifier | Read by |
|--------|------------|---------|
| Admin / member login | `admin`, `member-1` | auth, navigation |
| Curated member | `Alice Smith` (`alice.smith@example.com`, Resident) | users, a11y |
| Curated member | `Frank Miller` | users |
| Seeded item | `ITEM-0001` / `Test Item 0001` (and `0002`, `0003`, `0007`) | items, stock, PO |
| Item code sort scope | `ITEM-009` → `ITEM-0090`…`ITEM-0092` | items |
| Seeded item group | `Top Sellers Q1` | catalog |
| Seeded facility | `FAC-A` | stock, PO |
| Seeded draft POs | 6 draft purchase orders (read-only) | purchase-orders list |

The purchase-order lifecycle test creates its **own** per-run draft PO (harvesting
a real vendor id from a seeded PO's detail and an item id from the inventory list)
rather than consuming a seeded draft, so the seeded drafts remain stable anchors.

Because this database persists, schema changes are applied **forward** over its
seeded rows rather than by drop-and-recreate. When a migration lands, bring the
lane up to head and rebuild the snapshot as described in
[`../../docs/e2e-migrations.md`](../../docs/e2e-migrations.md), which also carries
the migration review checklist for changes that must be safe over seeded data.

### Added `data-testid` hooks

| testid | Template | Added by |
|--------|----------|----------|
| `kpi-card`, `kpi-value` | `templates/components/_kpi_card.html.twig` | S3 (LRA-165) |
| `activity-row`, `event-row` | `templates/dashboard/index.html.twig` | S3 (LRA-165) |
| `quick-tile` | `templates/cash_register/quick.html.twig` | S4 (LRA-166) |
| `combo-component-row` | `templates/inventory/combos/_form.html.twig` | S7 (LRA-169) |
| `po-detail-vendor-id` | `templates/inventory/purchase-orders/_detail_body.html.twig` | S9 (LRA-178) |

## Layout

```text
tests/e2e/
  setup/        # health gate + per-role login (storage state)
  support/      # auth, fixtures (page-error collector), downloads helpers
  smoke/        # S0 harness smoke
  …             # one folder per suite, added by S1–S12
```
