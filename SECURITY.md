# Security policy

## Reporting a vulnerability

Please do not report security vulnerabilities through public GitHub issues, discussions or pull requests.

Report them privately through [GitHub's private vulnerability reporting](https://github.com/phoenix1331/filament-headroom/security/advisories/new). Include the version, how to reproduce the issue and what an attacker could do with it. You should hear back within a few days, and fixes are released as soon as they are ready, with credit if you would like it.

## Supported versions

Until 1.0, only the latest release receives security fixes.

| Version | Supported |
|---|---|
| Latest 0.x | Yes |
| Older 0.x | No |

## What the dashboard exposes

Headroom for Filament stores nothing. It reads the [Headroom Ledger](https://github.com/phoenix1331/laravel-ai-headroom-ledger) through its query API and shows, for each AI provider call your app made:

- agent class name, provider and model, count method and outcome
- token counts (estimated, reported input, output, cache), context window and share used
- estimated and reported cost
- timestamps, the agent run (invocation) ID and the request ID
- the context tags your app chose to record, such as a tenant or user ID

The ledger never records prompt, instruction, tool or response text, and the dashboard cannot show what was never stored. Context tags are the part to think about: anyone who can open the dashboard can see every tag value in it. Only allowlist tags in `config/headroom-ledger.php` that the people with dashboard access may see.

## Authorisation defaults

| Control | Default | Change it with |
|---|---|---|
| Who sees pages and widgets | Nobody outside the `local` environment | `HeadroomPlugin::make()->authorize(fn ($user): bool => ...)` |
| Callback result | Only exactly `true` allows; `1`, `'yes'` and other truthy values deny | |
| Widgets loaded directly | Return 403 on mount and on every later request when the user may not see them | |
| Widgets on panels without the plugin | Local environment only | |
| Forgetting a tag value (GDPR action) | Off for everyone | `->allowForgettingTags(fn ($user): bool => ...)`, separate from `authorize()` |

Always set `authorize()` before deploying, for example to a role or an allowlist of administrators. The default exists so a forgotten setting fails closed, not to replace a real rule.

## Multi-tenant panels

On a Filament panel with tenancy, call `->scopeToTenant('tenant_id')` (or map the tenant to the value your app records: `->scopeToTenant('team', fn ($tenant) => $tenant->uuid)`). Then:

- every page, widget, table, filter option, agent run and detail view reads only the current tenant's entries
- a request ID from another tenant returns 404 on the detail page, exactly like one that does not exist
- with no current tenant, nothing matches
- the tag must be in the ledger's `tags.allowed` list, and your app must record it on every request (with `Headroom::resolveContextUsing()`); entries recorded without it are visible to no tenant

`tests/Feature/TenantIsolationTest.php` checks every page and widget for leaks between tenants.

The GDPR action is never available under tenant scoping. The ledger's `forgetTag()` erases a value across every tenant, so a tenant administrator could otherwise erase another tenant's data that happens to share the value. Run data subject requests from a non-tenant admin panel or with `Ledger::forgetTag()` in your own code.

## Data subject requests

With `allowForgettingTags()` enabled, the Requests page offers "Forget a tag value". It calls the ledger's `forgetTag()`, which deletes the tag value everywhere, clears it from daily rollups and gives the affected agent runs new random IDs so they no longer match your logs. It cannot be undone, and entries still on the ledger's queue when it runs are not covered, so run it once the queue has drained.
