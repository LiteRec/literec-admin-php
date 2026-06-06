# Migrations on the persistent E2E database

The E2E / human-testing lane (`app_e2e`, physical `app_e2e_test`) is a
**persistent** database — it is not dropped between runs (LRA-176). That changes
how schema changes reach it: instead of drop-and-recreate, a new migration is
applied **forward over the existing seeded rows**. This document covers how that
works, how to keep the restore snapshot in step with the schema, and the review
checklist every migration must satisfy so it is safe to roll forward over seeded
data.

For seeding, resetting, and the day-to-day workflow of this database, see the
E2E suite guide ([`../tests/e2e/README.md`](../tests/e2e/README.md)). For the
throwaway functional/integration database (`app_test`, rolled back per test) the
drop-and-recreate flow in the top-level README still applies; this document is
specifically about the **persistent** lane.

## How migrations reach the persistent database

`app:seed:e2e` (run via `composer seed:e2e`) is the forward-migrate entry point.
On an **already-seeded** database it:

1. ensures the database exists (`doctrine:database:create --if-not-exists`),
2. runs `doctrine:migrations:migrate` **forward, with no drop**, applying any
   pending migrations over the existing rows, and
3. stops — it does **not** reload fixtures and does **not** re-capture the
   snapshot, so a previous test run's mutations can never overwrite the baseline.

```bash
# Apply pending migrations to the persistent E2E database, preserving its data.
composer seed:e2e
```

So after pulling a branch that adds a migration, `composer seed:e2e` brings your
persistent database up to head without losing the data already in it.

> [!NOTE]
> **The database must already exist when `seed:e2e` runs.** Step 1's
> `doctrine:database:create --if-not-exists` is *not* the first thing the command
> does: `app:seed:e2e` first reads `Connection::getDatabase()` to guard the lane,
> and under doctrine/dbal 4 that is a live `SELECT CURRENT_DATABASE()` which fails
> with `SQLSTATE[08006] … database "app_e2e_test" does not exist` against a lane
> that was never provisioned. On a persistent developer machine the lane is
> created once (e.g. via `composer db:reset-e2e`, or the create step below) and
> stays, so this only bites **first-time** or **ephemeral** setups. Provision it
> first in those cases — CI does exactly this before seeding (LRA-179):
>
> ```bash
> php bin/console --env=test doctrine:database:create --if-not-exists
> ```

### Rebuild the snapshot after a schema change — always use `--fresh`

The fast `composer reset:e2e` restore (LRA-177) recreates the database from the
`app_e2e_test_snapshot` template. That snapshot is captured **only** when the
database is seeded from empty or when `--fresh` is passed — never by the plain
forward-migrate above. This is deliberate (it stops a dirty run from poisoning
the baseline), but it has a sharp edge:

> [!WARNING]
> A plain `composer seed:e2e` migrates the **live** database forward but leaves
> the **snapshot** at the previous schema. If you then run `composer reset:e2e`,
> it restores the old snapshot and **silently reverts your migration**. After any
> schema change, rebuild the snapshot.

Rebuild the snapshot (purge → reload `test` fixtures → migrate to head → capture)
with `--fresh`:

```bash
# Rebuild a clean baseline at the current schema and re-capture the snapshot.
composer seed:e2e -- --fresh
```

After this, `composer reset:e2e` restores a database whose schema matches head.

## Migration review checklist

Doctrine generates `migrations/VersionYYYYMMDDHHMMSS.php`. Before merging a
migration, confirm it is safe to apply **forward over a non-empty, seeded
table** — not just against a fresh schema. A reviewer should check:

- **Adding a `NOT NULL` column → it must carry a `DEFAULT`, or be backfilled.**
  `ALTER TABLE … ADD COLUMN … NOT NULL` fails on a table that already has rows
  unless a default supplies a value for them. Two safe shapes:
  - single statement with a default (preferred when a sensible default exists),
    as in LRA-99:

    ```sql
    ALTER TABLE inventory_items ADD COLUMN version INTEGER NOT NULL DEFAULT 0;
    ```

  - or add the column **nullable**, backfill every existing row with an explicit
    `UPDATE`, then `ALTER COLUMN … SET NOT NULL` — when the value must be derived
    rather than constant:

    ```sql
    ALTER TABLE foo ADD COLUMN slug VARCHAR(64);
    UPDATE foo SET slug = lower(name) WHERE slug IS NULL;
    ALTER TABLE foo ALTER COLUMN slug SET NOT NULL;
    ```

- **Dropping or renaming a column / table → account for seeded data and the
  anchor contract.** A column that anchors (LRA-178) or seeded fixtures depend on
  cannot just disappear; update the fixtures and the anchor table in
  `tests/e2e/support/anchors.ts` in the same change. Prefer expand-then-contract
  for renames (add new, backfill, switch readers/writers, drop old) so a
  half-applied state is never broken.
- **Widening is safe; narrowing needs a data check.** Growing a type
  (`VARCHAR(50) → VARCHAR(100)`, `INTEGER → BIGINT`, `NOT NULL → NULL`) applies
  cleanly over existing rows. Shrinking (`→ VARCHAR(20)`, adding `NOT NULL`,
  tightening a `CHECK`) can fail or truncate against seeded rows — verify the
  current data fits, and backfill/clean first if it does not.
- **`down()` must actually reverse `up()`.** The persistent lane is the one place
  rollbacks are exercised against real data (e.g. to test a forward-migrate); a
  `down()` that drops a column it did not add, or omits a step, corrupts the lane.
- **No migration may assume an empty table.** Anything that would only be correct
  on a fresh schema (e.g. recreating a table, or an `INSERT` of seed rows that a
  fixture also inserts) will double up or fail on the seeded lane. Seed data
  belongs in fixtures, not migrations.

## Verifying a migration against the seeded lane

Before merging a non-trivial migration, confirm it rolls forward over seeded data
without loss. The flow below was used to validate this path (LRA-180), using the
real LRA-99 `version`-column migration as the representative case:

```bash
# 1. Seed to head and snapshot.
composer seed:e2e

# 2. Step the schema back one migration (its down() runs); seeded rows remain.
DATABASE_URL='postgresql://app:!ChangeMe!@127.0.0.1:5432/app_e2e?serverVersion=17&charset=utf8' \
  php bin/console --env=test doctrine:migrations:migrate prev -n

# 3. Roll forward over the existing rows; data is preserved, no fixture reload.
composer seed:e2e

# 4. Confirm row counts are unchanged and the new column/default is in place,
#    e.g. via: php bin/console --env=test dbal:run-sql "SELECT count(*) FROM …"

# 5. Rebuild the baseline snapshot so reset:e2e matches the new schema.
composer seed:e2e -- --fresh
```

Observed on the LRA-180 validation run: rolling `Version20260528030000` (the
LRA-99 optimistic-lock `version` column) back and then forward left all seeded
rows intact (31 users, 93 inventory items, 13 purchase orders) and re-added the
`version` column with its `DEFAULT 0` applied to every pre-existing row — exactly
the "NOT NULL needs a default" rule above, demonstrated end to end.
