# literec-admin-php

The LiteRec Admin PHP Server. A parks and recreation management solution.

## Design system

The staff-admin UI follows **Organic**: a warm terracotta/sage palette on a
sand-neutral background, Caprasimo display headings over Figtree body text,
and a rounded, token-driven `lr-*` component layer defined in
`assets/styles/app.css`. Every screen supports both a light and a dark theme
via the header's theme toggle.

See `templates/components/README.md` for the full component reference
(Twig partials and `lr-*` CSS classes), and visit `/_dev/components` in the
`dev` or `test` environment for a live, side-by-side showcase of every
restyled component in both themes.

## Real TLS on local dev (Cloudflare DNS-01)

The shipped FrankenPHP image is rebuilt with the
[`caddy-dns/cloudflare`](https://github.com/caddy-dns/cloudflare)
provider so Caddy can solve the Let's Encrypt **DNS-01 challenge**
against a Cloudflare-managed zone. This gives every developer a real,
publicly-signed certificate on a chosen internal subdomain — without
opening port 80 or 443 to the internet.

Contributors without a Cloudflare token are unaffected: the build still
succeeds and Caddy falls back to its internal CA.

### Prerequisites

- A DNS zone you control inside a Cloudflare account.
- A **scoped** Cloudflare API token with **`Zone — DNS — Edit`** on the
  dev zone **only** (do not use a Global API Key, do not grant
  account-wide permissions).
- A hostname under that zone you want this container to answer on
  (e.g. `litrec.dev.example.com`).
- A DNS record in the Cloudflare zone for that hostname pointing to
  the machine running the container — an `A` / `AAAA` record to the
  host's public IPv4 / IPv6, or a `CNAME` to a host that already
  resolves to it. Without this record the browser cannot reach the
  container even after the certificate is issued.

### Configuration

1. Copy the token into `.env.local` (already in `.gitignore`, never
   commit it):

   ```bash
   CLOUDFLARE_API_TOKEN=cf_pat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   SERVER_NAME=litrec.dev.example.com
   CADDY_SERVER_EXTRA_DIRECTIVES="tls { dns cloudflare {env.CLOUDFLARE_API_TOKEN} }"
   ```

2. Rebuild the image so the xcaddy builder runs and the
   `caddy-dns/cloudflare` provider lands in the binary:

   ```bash
   docker compose build php
   ```

3. Start the stack and request the host:

   ```bash
   docker compose up -d
   curl -v https://litrec.dev.example.com/health
   ```

4. Verify the certificate issuer in your browser address bar (or via
   `openssl s_client -connect litrec.dev.example.com:443 -servername litrec.dev.example.com | grep issuer`).
   The issuer should be **Let's Encrypt**, not Caddy Local Authority.

### Verifying the module is compiled in

```bash
docker compose run --rm php frankenphp list-modules | grep dns.providers.cloudflare
```

This command must print `dns.providers.cloudflare`. If it does not, the
image build has regressed — the Dockerfile contains a build-time guard
that fails the image build in that case, so this should never reach
runtime.

## Data Fixtures

The repository ships Symfony Data Fixtures so any developer can land on
a fully seeded local database with one command. Fixtures are powered by
`doctrine/doctrine-fixtures-bundle` and are registered only in the
`dev` and `test` environments — the `prod` environment cannot load
fixtures.

### Quick start

```bash
# Drop, create, migrate, and reseed the dev database in one shot.
composer db:reset

# Same flow against the test database.
composer db:reset-test

# Load fixtures only (skip drop/migrate) — fast re-seed after schema is
# already in place.
composer fixtures:dev
composer fixtures:test
```

`make db-reset`, `make db-reset-test`, `make fixtures-dev`, and
`make fixtures-test` are thin wrappers around the composer scripts for
contributors who prefer the `make` workflow. `composer.json` remains
the single source of truth.

The dev and functional/integration databases are dropped and recreated by the
commands above. The **persistent** E2E / human-testing database (`app_e2e`) is
not — it is seeded once and restored from a snapshot, and schema changes roll
**forward** over its seeded rows. See
[`docs/e2e-database.md`](docs/e2e-database.md) for the operator guide (where it
lives, how to seed/reset it, the read-only anchor contract, and the seed-date
aging strategy), and [`docs/e2e-migrations.md`](docs/e2e-migrations.md) for the
forward-migrate flow and the migration review checklist.

### Available groups

| Group | Contents | Typical use |
|---|---|---|
| `dev` | curated personas + Faker-generated bulk users/households | local development browsing |
| `test` | curated personas + a small Faker batch (configurable counts) | integration/functional tests |
| `demo` | identical to `dev` today; reserved for demo-only data later | sales/demo environments |

Load a specific group via `bin/console doctrine:fixtures:load -n
--group=<group>` against the right `APP_ENV`.

### Curated personas

These named rows are deterministic and safe to reference from
integration tests. The class constants
`UsersFixtures::ADMIN_USERNAME` and
`UsersFixtures::CURATED_MEMBER_USERNAMES` are the source of truth for
the usernames.

| Context | Reference (DB value or name) | Scenario | Intended use |
|---|---|---|---|
| Users | `admin` (`ROLE_ADMIN`) | The admin persona | Sign in as an administrator |
| Users | `member-1` … `member-5` (`ROLE_USER`) | Five curated regular users | Sign in as a non-admin user |
| Households | `Smith Single` | Single-person resident household | One-member household scenarios |
| Households | `Jones Family` | Head + spouse + 2 children | Multi-member household scenarios |
| Households | `Miller Senior` | Senior household with a recorded `ChangeMemberResidency` transition | Residency-history projections |
| Households | `Brown Inactive` | One active member + one deactivated member | DeactivateMember-aware UI/tests |

Every curated user is registered with the shared password
`fixture-password-1234` (the literal value used by `UsersFixtures`).

### Configuration env vars

| Variable | Default | Effect |
|---|---|---|
| `FIXTURE_SEED` | `1` | Seeds FakerPHP (and the per-iteration re-seed inside each fixture) so the bulk dataset is byte-identical across runs. Change to generate a different but still stable dataset. |
| `FIXTURE_USER_COUNT` | `25` | Number of Faker-generated bulk users (capped at `UsersFixtures::MAX_BULK_COUNT` = 5000). Set to `0` to skip the bulk loop. |
| `FIXTURE_HOUSEHOLD_COUNT` | `25` | Number of Faker-generated bulk households (capped at `HouseholdsFixtures::MAX_BULK_COUNT` = 1000). Set to `0` to skip the bulk loop. |
| `MEMBER_PHOTO_DIR` | `%kernel.project_dir%/var/uploads/member-photos` | Root directory member profile photos (LRA-207) are written under. Only `/app/var` is writable in production; never point this at `public/` — photos are streamed through the authenticated `MemberPhotoController`, never served as static files. |

Determinism guarantee: with the same `FIXTURE_SEED` and the same
counts, every non-identity column (usernames, names, emails, gender,
residency, addresses, …) is reproducible run-to-run. Identity columns
(UUID v7) intentionally vary because v7 embeds the wall-clock
timestamp; this is verified by
`tests/Integration/Fixtures/DeterminismTest.php`.

### Architecture rule — DDD/Hex

Fixtures **must** write through the application layer — never
construct aggregates directly, never inject `EntityManagerInterface`
into a fixture class, never call a domain repository from a fixture.
Every persistence path goes through `MessageBusInterface::dispatch(new
RegisterUser(...))`, `dispatch(new RegisterHousehold(...))`,
`dispatch(new AddMemberToHousehold(...))`, etc. This keeps fixtures on
the same write path that production code uses and exercises the
command-bus middleware (validation, Doctrine transactions, domain
event dispatch).

The deptrac ruleset (`deptrac.yaml`) enforces the layering:
`App\Users\Infrastructure\Fixtures\*` and
`App\Households\Infrastructure\Fixtures\*` are part of their context's
`*Infrastructure` layer, which may depend on the matching `*Domain`
and `*Application` layers plus `Symfony\Component\Messenger`,
`Symfony\Component\PasswordHasher` (Users only),
`Psr\Clock`, `Doctrine\*`, `Doctrine\Common\Collections`,
`Symfony\*` (generic), `Faker`, and
`App\Shared\Infrastructure`. Cross-context imports
(e.g. Households fixtures importing `App\Users\*`) remain forbidden;
fixtures that need to order themselves after another context's
fixture reference the dependency by FQCN string in
`getDependencies()`.

### Static architecture checks

`composer stan` also runs [PHPat](https://github.com/carlosas/phpat), a
PHPStan extension, against a growing set of class-design rules registered
in `phpat.neon`. They ratchet the current class-design baseline — nothing
in `src/` had to change to add them — so any future violation fails
CI rather than waiting for code review to catch it:

| Rule class | `tests/Architecture/` file | Enforces |
|---|---|---|
| `classesInAppAreFinal` | `EverythingIsFinalRule.php` | Every concrete `App` class is `final` (interfaces, enums, traits, abstract classes, and `App\Kernel` are exempt). |
| `classesDoNotExtendAConcreteAppClass` | `NoConcreteInheritanceRule.php` | No `App` class extends another concrete (non-abstract, non-interface) `App` class; SPL/framework parents are unaffected. |
| `controllersDoNotDependOnDoctrine` | `ControllersStayThinRule.php` | Controllers under a context's `Infrastructure\Http` layer, plus the legacy `App\Controller` root controllers, never depend on `Doctrine\*` directly. |
| `handlersHaveOnlyOnePublicMethod` | `HandlersExposeOnePublicMethodRule.php` | Every `*Handler` class exposes exactly one public method, with four documented exceptions. |
| `domainAndApplicationDoNotConstructSplExceptions` | `NoSplExceptionsInDomainOrApplicationRule.php` | Domain and Application code never constructs a generic SPL exception (`\Exception`, `\RuntimeException`, etc.); extending one to define a named domain exception is still allowed. |
| `commandAndQueryDtosDoNotDependOnDomain`, `commandAndQueryDtosDoNotDependOnFrameworkTypes`, `commandAndQueryDtosAreReadonly` | `CommandAndQueryDtoPurityRule.php` | Every command/query DTO under a context's `Application\Command`/`Application\Query` namespace never depends on `Domain` code from any context, never depends on a framework namespace (`Symfony`, `Doctrine`, `Psr`, `Twig`), and is `readonly`. |
| `domainEventsAreReadonly`, `domainEventsAreNamedInThePastTense` | `DomainEventShapeRule.php` | Every class under a context's `Domain\Event` namespace is `readonly` and named in the past tense (regular `ed`, or an allow-listed irregular participle). |
| `doctrineRepositoriesImplementTheirContextDomainPort{Context}` | `DoctrineRepositoriesImplementDomainPortRule.php` | Every `App\<Context>\Infrastructure\Persistence\Doctrine` class (yielded once per context) implements a port from that same context's `Domain`. |

To add a rule: create a new class under `tests/Architecture/` with a
public method tagged `#[PHPat\Test\Attributes\TestRule]` that returns
`PHPat::rule()->...->because('...')`, then register the class as a
service tagged `phpat.test` in `phpat.neon`. `show_rule_names: true`
in that file makes `composer stan` output name the failing rule.

### Interaction with `dama/doctrine-test-bundle`

`dama/doctrine-test-bundle` wraps every functional test in a database
transaction that is rolled back at the end of the test. Fixtures
loaded once (typically by the manually-dispatched "Fixtures smoke test"
job in `.github/workflows/on-demand-tests.yml`, or a local
`composer db:reset-test`) remain visible inside every functional test
and roll back implicitly per test. The full `composer test` suite
does **not** load fixtures because the existing unit/integration/
functional tests are designed to run against an empty migrated DB —
the fixtures path is exercised in its own on-demand workflow job
(`Fixtures smoke test`, dispatched manually from the Actions tab) plus
the `slow`-tagged integration tests under `tests/Integration/*/Fixtures/`.

### Extending fixtures

To add a new fixture for a bounded context:

1. Place the fixture class under
   `src/<Context>/Infrastructure/Fixtures/`. Extend
   `Doctrine\Bundle\FixturesBundle\Fixture`; implement
   `FixtureGroupInterface` and `DependentFixtureInterface` if you
   need group membership or load ordering.
2. Inject `Symfony\Component\Messenger\MessageBusInterface` and
   `Faker\Generator` via the constructor — both are autowired in
   `dev` and `test`. Use
   `App\Shared\Infrastructure\Fixtures\FixtureEnv` for env reads
   (seed, bulk counts) to stay consistent with existing fixtures.
3. Dispatch existing `Application\Command\*` DTOs — never construct
   aggregates directly.
4. Re-seed Faker per iteration (`$this->faker->seed($baseSeed +
   $i);`) inside any bulk loop so output is reproducible regardless
   of how much process-wide `mt_rand` state the surrounding
   framework consumed between iterations.
5. Add an integration test under
   `tests/Integration/<Context>/Fixtures/` that uses
   `App\Tests\Support\Trait\TruncatesFixtureTables` to start from a
   clean DB and asserts the rows you care about.

### Troubleshooting

- **"Command not found: doctrine:fixtures:load"** — confirm
  `APP_ENV` is `dev` or `test`. The bundle is intentionally absent
  from `prod`.
- **DB out of sync after a schema change** — run `composer db:reset`
  (drops, creates, migrates, and reseeds in one step).
- **Faker output changed unexpectedly across runs** — check whether
  someone bumped `fakerphp/faker` minor version (different name
  pools), or whether `FIXTURE_SEED` was overridden in your
  environment. Reset with `unset FIXTURE_SEED`.
- **Deptrac fails on a new fixture** — confirm the fixture imports
  only `Application\Command\*` and shared infrastructure types,
  never `Domain\*` or another context's classes.
- **"Username is already taken" when running the slow tests** — the
  test forgot to call `$this->truncateFixtureTables(...)` before
  loading. See `App\Tests\Support\Trait\TruncatesFixtureTables`.

## License

This project is licensed under the [Elastic License 2.0](https://www.elastic.co/licensing/elastic-license) (SPDX: `Elastic-2.0`). See [LICENSE](LICENSE).

In short: you may use, copy, modify and redistribute the software free of charge, provided you keep all copyright and license notices. You may not provide it to third parties as a managed or hosted service, and you may not remove or circumvent any license-key functionality or licensing notices. Commercial hosting and support are offered by the copyright holder.
