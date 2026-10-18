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
- Estimate accuracy widget: weighted error between Headroom's estimates and reported input tokens per model over time, filterable by count method, with gaps where no requests reported usage.

[Unreleased]: https://github.com/phoenix1331/filament-headroom/commits/main
