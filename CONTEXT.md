# Context

**Headroom for Filament** (`phoenix1331/filament-headroom`) is a Filament panel plugin that shows AI token usage, cost, context headroom, estimate accuracy and warned/blocked/failed requests. It reads everything from the Headroom Ledger and stores nothing itself.

This is a Composer **package**, not a Laravel app. There is no `app/`, no routes, no Docker and no database server; it is tested inside Orchestra Testbench and previewed through the Testbench workbench, both on SQLite.

## Headroom family

| Package | Role |
|---|---|
| `phoenix1331/laravel-ai-headroom` | Counting, guards, calibration, events. Stores nothing |
| `phoenix1331/laravel-ai-headroom-ledger` | Records Headroom events, query API (`Ledger::query()`), rollups, report |
| `phoenix1331/filament-headroom` | **This package.** Filament pages and widgets over the ledger's query API |

## Architecture at a glance

| Path | Role |
|---|---|
| `src/HeadroomPlugin.php` | Filament `Plugin` (ID `headroom`): `make()`, `get()`, fluent options, registers pages and widgets on the panel |
| `src/FilamentHeadroomServiceProvider.php` | Auto-discovered; loads translations and views under the `filament-headroom` namespace |
| `src/Pages/` | Overview, Requests (table, filters, run grouping, detail view, GDPR action), Models |
| `src/Widgets/` | Stats overview, spend over time, tokens by agent, context headroom, estimate accuracy, blocked/warned/failed. Also usable on host dashboards |
| `resources/lang/en/` | Every user-facing string |
| `resources/views/` | Blade views where Filament components are not enough |
| `tests/` | Pest + Testbench with a Filament panel registered; arch tests enforce the data access rule |
| `workbench/` | Testbench workbench: Filament panel using the plugin, ledger migrations, seeder built on `LedgerEntryFactory`. Export-ignored |
| `docs/` | GitHub Pages site and screenshots. Export-ignored |

Only `src/HeadroomPlugin.php`, `src/FilamentHeadroomServiceProvider.php`, the `tests/` scaffolding and the workbench exist so far. Treat the rest of the table as the target layout.

## Decisions

| Decision | Why |
|---|---|
| Read the ledger only through its public query API (`Ledger` facade, `LedgerQuery`, `GroupedQuery`, `Data` value objects) | The ledger's models and tables are `@internal`. Enforced by a Pest arch test: no `DB::table`, no imports of `Phoenix1331\AiHeadroomLedger\Models` |
| Missing query capability goes back to the ledger as a proposed change | The ledger stays on 0.x until this plugin works; changing it is cheaper than coupling to its schema |
| Never present an estimate as billed cost | Ledger `Money` carries `estimated` and `approximate` flags; every widget and page labels them |
| Default deny outside `local` | Same as Horizon and Pulse, so the dashboard is never accidentally public |
| Require Headroom directly, not just via the ledger | The plugin uses Headroom enums such as `CountMethod` |
| No Docker, FrankenPHP, Makefile or MySQL | Package development only: PHP and Composer in WSL, Testbench on SQLite. Overrides the default stack in `.claude/rules/RULES.md` |
| Siblings from Packagist, not path repositories | Both are published at v0.1.0, and `../` paths would break CI. See local ledger changes below |
| `TestCase` sets `$enablesPackageDiscoveries = true` | Loads every Filament, Livewire, Headroom and ledger provider without a hand-kept list. `tests/Fixtures/TestPanelProvider.php` registers the default `admin` panel with the plugin |
| No PHPStan baseline, no inline `@var` overrides | Types are narrowed with runtime guards (e.g. `HeadroomPlugin::ensureInstance()`), so max level holds without suppressions |
| Core stays free and MIT | It is the shop window for the family; any paid features go in a separate add-on package |

## Dependencies and constraints

| Package | Constraint | Why |
|---|---|---|
| `php` | `^8.3` | Ledger and Headroom floor (Filament alone allows 8.2) |
| `filament/filament` | `^5.0` | Filament 5 with Livewire 4; needs `ext-intl` |
| `illuminate/contracts`, `illuminate/support` | `^12.0\|^13.0` | Ledger and Headroom range (Filament alone allows 11) |
| `phoenix1331/laravel-ai-headroom` | `^0.1` | Enums used directly |
| `phoenix1331/laravel-ai-headroom-ledger` | `^0.1` | Query API, `LedgerEntryFactory` for seeding |
| `laravel/pint` (dev) | `^1.32` | Code style; `pint.json` uses the `laravel` preset plus `declare_strict_types` |
| `larastan/larastan` (dev) | `^3.13` | PHPStan at `level: max` over `src/` (`phpstan.neon`, cache in `build/phpstan`) |
| `orchestra/testbench` (dev) | `^10.6\|^11.3` | Boots Laravel for Larastan, Pest and the workbench |
| `pestphp/pest` + `pest-plugin-laravel` + `pest-plugin-livewire` (dev) | `^4.1\|^5.3`, `^4.0\|^5.0`, `^4.0\|^5.0` | Tests, arch tests, `livewire()` helper for pages and widgets. Pest 5 needs PHP 8.4 and Laravel 13, so v4 is allowed for the Laravel 12 and PHP 8.3 CI cells. `allow-plugins.pestphp/pest-plugin` enabled |
| `husky` (npm dev) | `^9.1.7` | Tracked `.husky/` hooks: `commit-msg` (lowercase conventional commits, no scopes), `pre-commit` (`pint --test`, `osv-scanner --lockfile=composer.lock`). POSIX `sh` only |

## Workbench

| Piece | Detail |
|---|---|
| Commands | `composer build` (publish Filament assets, recreate SQLite, migrate, seed) \| `composer serve` (build, then `testbench serve` on port 8000) |
| Login | `/_workbench` auto-logs in as `demo@example.com` (password `password`) and lands on `/admin` |
| Panel | `workbench/app/Providers/Filament/AdminPanelProvider.php`: default `admin` panel, Filament Dashboard + `AccountWidget`, `HeadroomPlugin::make()` |
| Migrations | Testbench defaults plus `vendor/phoenix1331/laravel-ai-headroom-ledger/database/migrations` via `testbench.yaml` (the ledger provider only publishes them) |
| Seeder | `workbench/database/seeders/DatabaseSeeder.php`: 90 days x 3 tenants (`tenant_id` acme/globex/initech, `feature` tag) built on `LedgerEntryFactory`, every outcome, streamed, abandoned, failover, approximate cost, multi-step runs, then `headroom-ledger:rollup --from` |
| Database | `vendor/orchestra/testbench-core/laravel/database/database.sqlite` (rebuilt on every `composer build`) |

Seed through `testbench.yaml` `seeders:` only. Adding `--seed` to the `migrate-fresh` build step runs the seeder twice and fails on the unique user email.

## CI

| Workflow | Jobs |
|---|---|
| `.github/workflows/tests.yml` | `pint --test` \| matrix PHP 8.3/8.4/8.5 x Laravel 12/13 x prefer-lowest/prefer-stable \| coverage (pcov, `--min=90`, `shell: bash` for `tee`) \| dist archive check |
| `.github/workflows/static-analysis.yml` | Larastan with GitHub annotations |
| `.github/workflows/release.yml` | On `v*.*.*` tags: release notes from the matching `## [x.y.z]` section of `CHANGELOG.md`, fails if missing |
| `.github/dependabot.yml` | Composer weekly (dev grouped), npm monthly, Actions weekly (grouped) |

`.github/scripts/check-archive.sh` allows only `LICENSE`, `README.md`, `composer.json`, `src/`, `resources/`, `config/`, `database/` in `git archive`; extend it alongside `.gitattributes` when a new shipped directory appears. Lowest cell verified locally: Laravel 12.62, Testbench 10.6, Pest 4.3, Filament 5.0.

## Local setup

- PHP 8.4 in WSL with `intl` (`sudo apt install php8.4-intl`) and `pcov` for coverage
- `osv-scanner` on the PATH (`go install github.com/google/osv-scanner/cmd/osv-scanner@latest`) for the pre-commit hook
- `npm install` once to activate the Husky hooks
- `composer install`, then the composer scripts (`lint`, `lint:test`, `analyse`, `test`, `test:coverage`, `check`). `check` runs `lint:test`, `analyse` and `test:coverage` and is the pre-push gate
- `composer.lock` is gitignored, as for any library

### Working against a local ledger checkout

When a ledger change is needed, point Composer at `../laravel-ai-headroom-ledger` temporarily and never commit it:

```bash
composer config repositories.ledger path ../laravel-ai-headroom-ledger
composer require "phoenix1331/laravel-ai-headroom-ledger:dev-main as 0.1.99"
```

Undo with `composer config --unset repositories.ledger` and `composer require phoenix1331/laravel-ai-headroom-ledger:^0.1` once the ledger change is tagged.
