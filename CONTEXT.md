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
| `src/Concerns/` | `AuthorisesPageAccess` (`canAccess()`) and `AuthorisesWidgetView` (`canView()`), both delegating to `HeadroomPlugin::allows()`; every Headroom page and widget uses one. `HasHeadroomNavigation` reads group, icon and sort from the plugin on every page |
| `src/Enums/Range.php` | `7d` / `30d` / `90d`: labels, `between()` (inclusive days ending today), `apply(LedgerQuery)`, `resolve($state)` falling back to the plugin's `defaultRange()` |
| `src/Support/Cost.php` + `src/Enums/ValueBasis.php` | Every cost goes through `Cost::fromMoney()` / `fromSpend()` / `fromSummary()`: formatted per currency, with a basis (Reported, Estimated, IncludesEstimates, Approximate) that carries badge label, tooltip and colour |
| `resources/views/components/cost.blade.php` | `<x-filament-headroom::cost :cost="$cost" />`: amount plus Filament badge unless the basis is Reported |
| `src/Concerns/QueriesLedger.php` | Widgets: `range()` from the page's `range` filter (else plugin default) and `ledger()` / `ledgerBase()` as the single starting point for every ledger read (tenant scoping hooks in here) |
| `src/Support/Format.php` | Locale-aware `number()` and `percent()` ("No data" for null) |
| `src/Widgets/StatsOverview.php` | Spend (trend vs previous period, sparkline, single currency only), requests, average/peak context, blocked, warned. No polling |
| `src/Widgets/SpendOverTime.php` | Line chart per model/provider (filter), top `ChartPalette::slots()` series plus "Other" = daily total minus drawn series; largest currency drawn, others named |
| `src/Widgets/TokensByAgent.php` | Stacked horizontal bars of reported input/output per agent (top `MAX_AGENTS`, rest folded); unreported requests counted in the description, never drawn |
| `src/Widgets/ContextHeadroom.php` | Distribution of pre-flight percent used in `EDGES` buckets, each from `nearLimit($edge)->count()` differences (one raw count per edge; ledger L2 would make it one query). Closest-to-limit list can now use `orderBy(EntrySort::PercentUsed)` |
| `src/Widgets/EstimateAccuracy.php` | `errorPercent()` per model per `Range::buckets()` point (1, 3 or 7 days) from one `byDay()->accuracy()` query merged per bucket; count method filter; null (gap) where a bucket has no samples |
| `src/Widgets/BlockedWarnedFailed.php` | Table widget over array records (`key` = request ID) from `withOutcome(...)->entries(LIMIT)` |
| `src/Support/Outcomes.php` | Outcome label and badge colour; `reason()` turns a `LimitType` value or `ExceptionClass:status` into words |
| `src/Support/RangeSelect.php` | `Select` for a dashboard's filters form under the `range` key (`RangeSelect::NAME`) that `QueriesLedger` reads; default from the plugin |
| `src/Support/ChartPalette.php` | Six categorical slots + neutral "Other", light and dark, validated with the dataviz palette validator on `#ffffff` / `#18181b`; a Chart.js scriptable picks the mode at draw time. Slots go by series name, not rank |
| `src/Pages/HeadroomPage.php` | Abstract base: extends Filament's `Dashboard` (filters form + widget grid) with page access, plugin navigation and `RangeSelect`; each page sets its own `$routePath` so the panel dashboard is untouched |
| `src/Pages/Overview.php` | `headroom`: every enabled widget |
| `src/Pages/Models.php` + `src/Widgets/ModelsTable.php` | `headroom/models`: one row per model (array records, sorted in PHP); not in `WIDGETS`, so `canView()` is authorisation only. Peak context and bias show as column descriptions to keep the table narrow |
| `src/Support/CalibrationStatus.php` | Headroom `Calibrator::correctionFor()` / `store()->get()` plus `ai-headroom.calibration` config: off, none, learning (n of min samples), active (factor, margin) |
| `src/Pages/Requests.php` | `headroom/requests`: plain `Page` + `HasTable` with an `EmbeddedTable`; array records from `paginate()` wrapped in a `LengthAwarePaginator`; `SORTS` maps sortable columns to `EntrySort`. `SelectFilter`s carry state only (custom-data tables ignore their queries); `query($filters)` maps it to `forAgent()`, `forProvider()`, `forModel()`, `withOutcome()`, `forCountMethod()`, `forTag()`. Tag filters (`tag_<key>`) come from `headroom-ledger.tags.allowed`; option lists from the last 90 days so they do not shift with the range |
| `src/Pages/Concerns/GroupsByRun.php` | Requests run view (`?group=run`, Livewire `#[Url]` so each mode builds its own table): `byRun()` spend/tokens/context totals under the same filters, paged in PHP; default order by most recent activity from time-sorted `paginate()` batches (`RUN_SCAN_BATCH`), never from invocation ID format; agent/start/end from the filter-scoped `run()` for current-page rows only |
| `src/Pages/RequestDetail.php` | `headroom/requests/{requestId}` (`getRoutePath()` override keeps the route name `headroom.requests.view`); only `$requestId` is Livewire state, the entry and run load once per request via `HeadroomPlugin::ledger()->find()` / `run()` (404 when out of scope); added by `register()` whenever a Requests page (or subclass) is enabled, not listed in `PAGES` |
| `resources/views/run-steps.blade.php` | Run steps table on the detail page: estimated input and growth from the previous step, current step `aria-current` |
| `resources/dist/filament-headroom.css` | `fi-headroom-*` classes on Filament colour variables, registered with `FilamentAsset` (`filament:assets` publishes it) |
| `src/Pages/` | Still to come: GDPR action on Requests |
| `src/Widgets/` | Stats overview, spend over time, tokens by agent, context headroom, estimate accuracy, blocked/warned/failed. Also usable on host dashboards |
| `resources/lang/en/` | Every user-facing string |
| `resources/views/` | Blade views where Filament components are not enough |
| `tests/` | Pest + Testbench with a Filament panel registered; arch tests enforce the data access rule. `tests/Concerns/WithLedgerDatabase.php` migrates the ledger tables for tests that need data; `tests/Fixtures/` holds pages and widgets that exercise the concerns |
| `workbench/` | Testbench workbench: Filament panel using the plugin, ledger migrations, seeder built on `LedgerEntryFactory`. Export-ignored |
| `docs/` | GitHub Pages site and screenshots. Export-ignored |

Built so far: `src/HeadroomPlugin.php`, `src/FilamentHeadroomServiceProvider.php`, `src/Concerns/`, the `tests/` scaffolding and the workbench. Treat the rest of the table as the target layout.

## Decisions

| Decision | Why |
|---|---|
| Read the ledger only through its public query API (`Ledger` facade, `LedgerQuery`, `GroupedQuery`, `Data` value objects) | The ledger's models and tables are `@internal`. Enforced by a Pest arch test: no `DB::table`, no imports of `Phoenix1331\AiHeadroomLedger\Models` |
| Missing query capability goes back to the ledger as a proposed change | The ledger stays on 0.x until this plugin works; changing it is cheaper than coupling to its schema |
| Never present an estimate as billed cost | Ledger `Money` carries `estimated` and `approximate` flags; every widget and page shows them through `Cost` and `ValueBasis`. Estimate outranks approximate. `SpendSummary` decides wholly estimated (no usage reported) vs includes estimates. Plain-text contexts (chart tooltips) use `Cost::label()`: "$1.24 (estimate)" |
| Default deny outside `local` | Same as Horizon and Pulse, so the dashboard is never accidentally public |
| `authorize()` callback must return exactly `true` | Truthy values (`1`, `'yes'`) deny, so a sloppy callback fails closed |
| Navigation sort is a base plus each page's own `$navigationSort` | One option moves the whole Headroom block while keeping page order; an icon can be set for all pages or per page |
| Every page and widget gets its range through `Range::resolve()` | One place for the allowed ranges, their labels and the plugin default; invalid `defaultRange()` strings throw when set, not when a page renders |
| `AuthorisesWidgetView` aborts 403 on mount and every hydrate when `canView()` is false | Protects widget data even when mounted directly or after access is revoked. Filament 5.10 checks on mount itself, but older Filament 5 releases (CI prefer-lowest) do not, so the plugin enforces it |
| Chart options are `RawJs` with every literal from `Js::from()` | Filament renders chart options inside an HTML attribute; a `"` from `json_encode()` ends it and prints the JS as text. A test asserts the options contain no double quote |
| Token charts show reported tokens only | `TokenTotals` input/output cover only requests that reported usage, and the ledger cannot split estimate-only input per group; mixing in `estimatedInput` would double count |
| Tests use the array cache (`phpunit.xml` `CACHE_STORE=array`) | Headroom keeps calibration in the cache; Testbench's default database cache has no table in the test database |
| Plugin markup uses its own `fi-headroom-*` CSS, never Tailwind utilities | Filament's compiled CSS only contains the utilities Filament itself uses; a test keeps the stylesheet to `fi-headroom-` selectors |
| Tables over ledger data use array records | Filament tables take `->records(fn () => [...])` with a `key` per row; `assertCountTableRecords()` needs an Eloquent query, so tests assert visible records instead |
| Per-point charts bucket the range (`Range::bucketDays()`: 1 / 3 / 7) | Smooths noisy daily accuracy; a test caps the accuracy chart at 3 queries for any range |
| Run view pages runs in PHP | The ledger has no run pagination (proposed L3); it costs one grouped query per total plus one `run()` per visible row |
| Spend over time costs one query per drawn series | The ledger has no two-level grouping (day x model); proposed as L1 in the ledger's `TASKS-2.md` |
| Trends compare one currency only | Different currencies are never added or compared; multi-currency spend shows each amount with no trend or sparkline |
| Widgets are never added with `$panel->widgets()` | That list is the panel's own dashboard; Headroom widgets appear on the Overview page or wherever a host places them, and `widgets()` gates them through `canView()` |
| `pages()` / `widgets()` accept any Page / Widget subclass | Users customise by subclassing a built-in part; `HeadroomPlugin::PAGES` and `WIDGETS` hold the defaults |
| Every ledger read starts from `HeadroomPlugin::ledger()` | One place to add scoping (tenants) for pages and widgets alike; widgets reach it through `QueriesLedger::ledgerBase()` |
| `HeadroomPlugin::current()` returns null on panels without the plugin | Widgets on host dashboards still get the local-only default instead of throwing |
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
| `phoenix1331/laravel-ai-headroom-ledger` | `^0.1.1` | Query API (0.1.1 adds `orderBy(EntrySort)`, `paginate()` / `LedgerPage`, `forCountMethod()`, filter-scoped `find()` / `run()`, `GroupedQuery::accuracy()`), `LedgerEntryFactory` for seeding |
| `laravel/pint` (dev) | `^1.32` | Code style; `pint.json` uses the `laravel` preset plus `declare_strict_types` |
| `larastan/larastan` (dev) | `^3.13` | PHPStan at `level: max` over `src/` and `tests/Fixtures/` (`phpstan.neon`, cache in `build/phpstan`); fixtures are analysed so traits used only by them yet are checked |
| `orchestra/testbench` (dev) | `^10.6\|^11.3` | Boots Laravel for Larastan, Pest and the workbench |
| `pestphp/pest` + `pest-plugin-laravel` + `pest-plugin-livewire` (dev) | `^4.1\|^5.3`, `^4.0\|^5.0`, `^4.0\|^5.0` | Tests, arch tests, `livewire()` helper for pages and widgets. Pest 5 needs PHP 8.4 and Laravel 13, so v4 is allowed for the Laravel 12 and PHP 8.3 CI cells. `allow-plugins.pestphp/pest-plugin` enabled |
| `husky` (npm dev) | `^9.1.7` | Tracked `.husky/` hooks: `commit-msg` (lowercase conventional commits, no scopes), `pre-commit` (`pint --test`, `osv-scanner --lockfile=composer.lock`). POSIX `sh` only |

## Workbench

| Piece | Detail |
|---|---|
| Commands | `composer build` (publish Filament assets, recreate SQLite, migrate, seed) \| `composer serve` (build, then `testbench serve` on port 8000) |
| Login | `/_workbench` auto-logs in as `demo@example.com` (password `password`) and lands on `/admin/headroom` (Overview) |
| Panel | `workbench/app/Providers/Filament/AdminPanelProvider.php`: default `admin` panel, `HeadroomPlugin::make()`, and a host-style dashboard (`workbench/app/Filament/Pages/Dashboard.php` with `RangeSelect`) placing `StatsOverview`, as an app would |
| Migrations | Testbench defaults plus `vendor/phoenix1331/laravel-ai-headroom-ledger/database/migrations` via `testbench.yaml` (the ledger provider only publishes them) |
| Config | `workbench/app/Providers/WorkbenchServiceProvider.php` allowlists `tenant_id` (primary tag) and `feature`; the panel's `tagLabel()` shows tenants as company names |
| Seeder | `workbench/database/seeders/DatabaseSeeder.php`: 90 days x 3 tenants (`tenant_id` acme/globex/initech, `feature` tag) built on `LedgerEntryFactory`, every outcome, streamed, abandoned, failover, approximate cost, multi-step runs, then `headroom-ledger:rollup --from` |
| Database | `vendor/orchestra/testbench-core/laravel/database/database.sqlite` (rebuilt on every `composer build`) |

`testbench.yaml` lists `FilamentHeadroomServiceProvider` because Testbench does not auto-discover the root package (tests list it in `TestCase`). Composer's `post-autoload-dump` purges the skeleton, deleting the workbench database: run `composer build` after any `composer update`.

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
