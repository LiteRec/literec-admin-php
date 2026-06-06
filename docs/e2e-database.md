# The human / E2E database

A single **persistent, seeded** Postgres database backs both manual human testing
and the automated Playwright E2E suite. Unlike the dev and
functional/integration databases, it is **not** dropped between runs (LRA-176),
so a tester can log in, click around, and find the same seeded world each time,
and the E2E suite can run repeatedly without a rebuild.

This guide is the operator reference for that database: where it lives, how to
seed and reset it, the read-only data contract, and how seeded dates age. For
applying schema changes to it, see
[`e2e-migrations.md`](e2e-migrations.md); for the Playwright suite itself, see
[`../tests/e2e/README.md`](../tests/e2e/README.md).

## Which database

The application uses three database lanes, separated by `APP_ENV` and the
`DATABASE_URL` base name (the `test` env appends a `_test` suffix):

| Lane | `APP_ENV` | `DATABASE_URL` base | Physical database | Lifecycle |
|------|-----------|---------------------|-------------------|-----------|
| dev | `dev` | `app` | `app` | drop & reseed on demand (`composer db:reset`) |
| functional / integration | `test` | `app` | `app_test` | created once, each test rolled back |
| **human / E2E** | `test` | `app_e2e` | **`app_e2e_test`** | **persistent**, seeded once, restored from snapshot |

The E2E lane runs under `APP_ENV=test` but with a distinct base name (`app_e2e`),
so its physical database `app_e2e_test` never collides with `app_test` (the
rolled-back functional lane) or `app` (dev). E2E mutations therefore can't
disturb the other lanes.

### How the lane is selected — `DATABASE_URL`

The lane is chosen entirely by `DATABASE_URL`; nothing else needs changing:

- **Locally**, `playwright.config.ts` and the `composer seed:e2e` / `reset:e2e`
  scripts default to the `app_e2e` connection, so the suite and the managed PHP
  server both target `app_e2e_test` without any manual export.
- **In CI**, the E2E job sets `DATABASE_URL` to its Postgres service at the same
  `app_e2e` base name (LRA-179), so the run is identical to local.
- To point at a different database (for example a shared docker stack), export
  `DATABASE_URL` before invoking the commands, or set `E2E_BASE_URL` to run the
  suite against an already-running instance.

## Seeding and resetting

`composer seed:e2e` and `composer reset:e2e` carry the `app_e2e` `DATABASE_URL`
themselves, so no manual export is needed.

```bash
# First-time setup: provision the lane database, then seed it and capture the
# restore snapshot. The create step is required because app:seed:e2e introspects
# the connection to guard the lane before it can create the database — see the
# bootstrap note in docs/e2e-migrations.md.
php bin/console --env=test doctrine:database:create --if-not-exists
composer seed:e2e          # migrate forward + load `test` fixtures + capture snapshot

# Fast reset between runs (seconds): restore the captured snapshot.
composer reset:e2e

# Rebuild a clean baseline at the current schema and re-capture the snapshot
# (purges + reloads fixtures). Use after a migration or whenever the data drifts.
composer seed:e2e -- --fresh
```

What each command does:

- **`composer seed:e2e`** — idempotent. Migrates the database **forward** (no
  drop), and on an empty/`--fresh` database loads the `test` fixtures and
  captures the `app_e2e_test_snapshot` template. On an already-seeded database it
  migrates forward and stops, leaving the data and snapshot untouched so a dirty
  run can't poison the baseline.
- **`composer reset:e2e`** — restores `app_e2e_test` from the snapshot in seconds
  via a Postgres `CREATE DATABASE … TEMPLATE` clone (LRA-177). Requires a snapshot
  captured by a prior `seed:e2e` (empty or `--fresh`).
- **`composer db:reset-e2e`** — the hard drop/create/migrate/load path. It does
  **not** capture a snapshot, so follow it with `composer seed:e2e -- --fresh` if
  you want `reset:e2e` to work afterward.

Applying migrations to the persistent database and rebuilding the snapshot
afterward is covered in [`e2e-migrations.md`](e2e-migrations.md).

## Read-only anchor data

Because the database persists, manual testing and the suite share one seeded
world. The contract that keeps it stable (LRA-178):

- A small set of **anchors** — immutable seeded rows (the `admin` / `member-1`
  logins, curated members like `Alice Smith`, seeded items `ITEM-0001…`, facility
  `FAC-A`, the six read-only draft purchase orders) — must **not** be edited when
  testing by hand. The suite reads only these.
- Every mutating flow creates its **own** per-run row (uniquely named) or asserts
  a delta, never an absolute count, so manual edits and prior runs don't break it.

The authoritative anchor list is in
[`../tests/e2e/README.md`](../tests/e2e/README.md#anchor-data-persistent-db) and
in code under `tests/e2e/support/anchors.ts`. When testing by hand, create new
rows freely; just leave the anchors alone.

## Clock and seed-date aging

### The drift

Seeded rows are written **once** and then frozen, while the running application's
clock keeps advancing toward the real "today". The deterministic `FixedClock`
(`2025-01-15T12:00:00Z`) and seeded identity generators are wired only under
`when@dev`; the E2E lane runs under `APP_ENV=test`, so the **real** clock and real
UUIDs are in effect. Seeded `created_at` / `sent_at` values therefore capture the
*instant the database was seeded*, not a pinned date — and they do not move after
that.

Surfaces that read relative to "today" drift as real time passes:

- **Purchase-order ETA and timeline** — a sent PO's estimated arrival is
  `sent_at + 3 days` (seeded from the clock at seed time), so once three days pass
  it reads as overdue/in the past.
- **Member residency `effectiveFrom`** — the change-residency form defaults to
  "today", and seeded residency dates sit at seed time.
- **Any "as of today" stock view** — the low-stock *count* threshold is static, so
  it is largely unaffected, but date-stamped stock movements age like the above.

### Decision: accept the drift, refresh the snapshot periodically

We **accept** seed-date drift for relative-to-today surfaces and re-stamp it by
**rebuilding the snapshot periodically** (`composer seed:e2e -- --fresh`, which
reloads the fixtures against the current clock), rather than introducing a
clock-offset shim into the running application.

**Rationale / trade-off:**

- *For (chosen):* it keeps the runtime simple and honest — the app uses the same
  real clock it uses in production, with no app-wide time indirection to reason
  about, and the database stays a genuine persistent store. A `--fresh` rebuild
  re-stamps every seed-time date to the current "now" in one step.
- *Against:* until the next rebuild, ETAs and effective dates read as if they are
  in the past. This is cosmetic for most manual testing and the E2E suite asserts
  deltas/relative state rather than absolute dates, so it does not break tests.
- *Mitigation — rebuild cadence:* re-run `composer seed:e2e -- --fresh` whenever
  the dates have drifted far enough to confuse a tester, and in any case alongside
  the post-migration snapshot rebuild (see [`e2e-migrations.md`](e2e-migrations.md)).
  Each rebuild brings the seeded dates back to the present.

**Alternatives considered and rejected (for now):**

- *A clock-offset shim in the running app* (serve a clock that shifts seeded dates
  to stay "fresh"): rejected — it adds app-wide time indirection, diverges from
  production behaviour, and complicates reasoning, for a purely cosmetic gain.
- *Re-seeding on every run:* defeats the purpose of a persistent lane (LRA-176)
  and is slower than a snapshot restore.

Revisit this only if a future surface needs seeded dates to track "today" exactly
(for example a report keyed on the current month); at that point a narrowly scoped
relative-date fixture strategy would beat a global clock shim.
