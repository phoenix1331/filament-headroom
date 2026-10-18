# Contributing

Thanks for helping improve Headroom for Filament. Bug reports, fixes, translations and new widgets are all welcome.

Please follow the [code of conduct](CODE_OF_CONDUCT.md). For anything large, open an issue first so we can agree the approach before you spend time on it. Security issues go through [SECURITY.md](SECURITY.md), never a public issue.

## Setup

You need PHP 8.3 or newer with `intl`, `pdo_sqlite` and (for coverage) `pcov`, Composer, Node (for the git hooks) and [`osv-scanner`](https://github.com/google/osv-scanner) on your `PATH`.

```bash
git clone https://github.com/phoenix1331/filament-headroom.git
cd filament-headroom
composer install
npm install          # activates the Husky commit-msg and pre-commit hooks
composer build       # builds the workbench: Filament assets, SQLite, ledger tables, demo data
composer serve       # http://127.0.0.1:8000/_workbench logs you in and opens the dashboard
```

There is no Docker and no database server: tests and the workbench both run on SQLite through [Orchestra Testbench](https://packages.tools/testbench).

## Composer scripts

| Script | What it does |
|---|---|
| `composer lint` | Fix code style with Pint |
| `composer lint:test` | Check code style without changing files |
| `composer analyse` | Larastan at max level over `src/` and `tests/Fixtures/` |
| `composer test` | The Pest suite |
| `composer test:coverage` | The suite with the 90% coverage gate on `src/` |
| `composer check` | `lint:test`, `analyse` and `test:coverage`; run it before every pull request |
| `composer build` | Rebuild the workbench (also needed after any `composer update`, which resets it) |
| `composer serve` | Build, then serve the workbench |
| `composer screenshots` | Capture every page and widget, light and dark, into `docs/screenshots/` |

## The workbench

`workbench/` is a small Filament app with the plugin installed: a panel, a demo user and a seeder that writes 90 days of realistic ledger entries for three tenants through the ledger's `LedgerEntryFactory` (every outcome, failover, approximate costs, multi-step agent runs). Use it to see a change before you test it.

- `/_workbench` logs in as `demo@example.com` and opens the Headroom Overview
- The panel's own dashboard shows a Headroom widget placed the way a host app would
- `workbench/app/Providers/WorkbenchServiceProvider.php` allowlists the `tenant_id` and `feature` tags, so the Requests page offers tag filters

## How the code is organised

Read `CONTEXT.md` for the map. The rules that matter most:

- **Read the ledger only through its public query API.** Every read starts from `HeadroomPlugin::ledger()`, which applies tenant scoping. No `DB` facade, no ledger models: an architecture test fails the build if you try. If the API cannot answer a question, propose a change to [the ledger](https://github.com/phoenix1331/laravel-ai-headroom-ledger) rather than working around it.
- **Never show an estimate as billed cost.** Every amount goes through `Support\Cost`, which carries the estimated or approximate basis and renders its badge.
- **Every page and widget is authorised.** Pages use `AuthorisesPageAccess`; widgets use `AuthorisesWidgetView`, which also refuses to mount for users who may not see them. New parts must also join the datasets in `tests/Feature/TenantIsolationTest.php`.
- **Plugin markup uses `fi-headroom-*` classes** from `resources/dist/filament-headroom.css`, not Tailwind utilities: Filament only ships the utilities it uses itself.
- **Charts use `Support\ChartPalette`**, whose colours are validated for colour vision deficiency on both themes, and build their options with `Js::from()` so no double quote breaks the HTML attribute Filament puts them in.

## Tests

Every change needs tests, and `composer check` must pass. Pest feature tests run inside Testbench with a Filament panel registered (`tests/Fixtures/TestPanelProvider.php`); use `uses(WithLedgerDatabase::class)` and `LedgerEntryFactory` for ledger data. Pin factory dates (`->at(...)`), because the factory's random dates can fall outside a range and make a test flaky.

Widgets need tests for an empty ledger, seeded data, the page range, authorisation and estimate labelling.

## Translations

Every user-facing string lives in `resources/lang/en/`. `tests/Feature/TranslationsTest.php` fails if a label, heading or description is a plain string, if a view contains literal text, or if code uses a key missing from `en`.

To add a language, copy `resources/lang/en` to `resources/lang/<locale>`, translate the values and keep the keys and `:placeholders`. Apps can also publish the English files and edit them in place:

```bash
php artisan vendor:publish --tag=filament-headroom-translations
```

## Screenshots

`composer screenshots` regenerates `docs/screenshots/` from the seeded workbench. It needs Google Chrome or Chromium (set `CHROME` to point at another binary) and a free port (`SCREENSHOT_PORT`, default 8000). Run `composer build` first, and include updated screenshots in pull requests that change how something looks, in light and dark mode.

## Commits and pull requests

- Commit messages are lowercase [Conventional Commits](https://www.conventionalcommits.org) without scopes, for example `feat: add spend widget`. The `commit-msg` hook enforces this.
- The `pre-commit` hook runs Pint and `osv-scanner` against `composer.lock`.
- Add a line under `## [Unreleased]` in `CHANGELOG.md` for anything users will notice.
- Write in UK English.
