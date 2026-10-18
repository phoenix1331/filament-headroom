# Changelog

All notable changes to `phoenix1331/filament-headroom` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `HeadroomPlugin::authorize()` to control who sees the dashboard, with a closure (receiving `$user`) or a boolean. Access is denied outside the `local` environment by default.
- `navigationGroup()`, `navigationIcon()` (for every page, or one page) and `navigationSort()` plugin options. Pages are grouped under "Headroom" by default.
- `defaultRange()` plugin option (`7d`, `30d` or `90d`, default `30d`) and the `Range` enum shared by every page and widget.
- `tagLabel()` plugin option to control how ledger tags are shown, for example a tenant name instead of its ID.
- `pages()` and `widgets()` plugin options to disable built-in parts or swap in subclasses. Disabled pages are not registered and disabled widgets stay hidden on every dashboard of the panel.
- Cost labelling shared by every page and widget: amounts per currency (four decimals below one cent, never converted), with an "Estimate", "Includes estimates" or "Approximate" badge and explanation whenever a figure is not billed cost. Available as the `<x-filament-headroom::cost>` component.
- Stats overview widget: spend with its trend against the previous period and a daily sparkline, request count, average and peak context used, and blocked and warned counts for the selected range.
- Spend over time widget: daily spend per model or provider, the top six as their own lines and the rest as "Other", in one currency (others named, never converted), marked when it includes estimates. Colours are validated for colour vision deficiency in light and dark mode.
- Tokens by agent widget: reported input and output tokens per agent as stacked bars, busiest first, with requests that never reported usage counted in the description instead of drawn.
- Context headroom widget: how much of the context window requests used, from pre-flight counts, in buckets up to 100%+ (did not fit), with the number at 90% or more called out.
- Closest to the limit widget: the five requests in the range that used the most of their context window and were still sent, linking to their detail pages.
- Estimate accuracy widget: weighted error between Headroom's estimates and reported input tokens per model over time, filterable by count method, with gaps where no requests reported usage.
- Blocked, warned and failed widget: the latest ten such requests in the range with outcome badges and readable reasons (limit type, or exception and HTTP status).
- Every widget works on an app's own Filament dashboards, following the plugin's authorisation and enabled widgets, or the local-only default on panels without the plugin. `RangeSelect::make()` adds the range picker to any dashboard's filters form.
- Overview page at `/headroom` (titled "AI usage") with the range picker and every enabled widget, leaving the panel's own dashboard untouched.
- Models page at `/headroom/models`: per-model requests, spend (labelled), average and peak context, blocked and warned counts, estimate error and bias, and Headroom calibration status (off, no data yet, learning, or active with its correction).
- Requests page at `/headroom/requests`: every ledger entry in a read-only table, paginated and sorted by the ledger (time, input and output tokens, context used, cost), with estimated input and cost labelled. Filters for range, agent, provider, model, outcome, count method and each allowlisted ledger tag (named through `tagLabel()`), remembered for the session.
- "Group by run" on the Requests page (`?group=run`): one row per agent run with steps, reported tokens, peak context, cost and how many steps warned, blocked or failed, under the same filters, ordered by most recent activity or any total.
- Request detail page (`/headroom/requests/{requestId}`), opened from any row: outcome and reason, counted and answering models (failover shown), count method, timing, tags through `tagLabel()`, estimated against reported input with the error, output and cache tokens, context used, per-part input breakdown, reported cost beside the labelled pre-flight estimate, and for agent runs every step with its context growth. Registered whenever the Requests page is.
- `scopeToTenant($tag, $value = null)` plugin option: on tenant panels, every page, widget, filter option, run and detail view reads only the current tenant's ledger entries, through a ledger context tag holding the tenant key (or a value mapped from the tenant). With no current tenant nothing matches.
- `allowForgettingTags($condition)` plugin option and a "Forget a tag value" action on the Requests page for data subject requests: erases one context tag value through the ledger's `forgetTag()` after confirmation. Off by default, authorised separately from the dashboard, and never available under tenant scoping because the ledger erases the value across every tenant.
- Plugin stylesheet (`filament-headroom`), published with `php artisan filament:assets`.

### Changed

- Requires `phoenix1331/laravel-ai-headroom-ledger` ^0.1.1.

### Fixed

- The Blocked stat no longer says every block was a context window overflow; requests are also blocked by cost and percentage limits.
- Estimate and approximate badges beside costs never truncate in narrow table columns.

[Unreleased]: https://github.com/phoenix1331/filament-headroom/commits/main
