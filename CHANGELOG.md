# Changelog

All notable changes to `phoenix1331/filament-headroom` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `HeadroomPlugin::authorize()` to control who sees the dashboard, with a closure (receiving `$user`) or a boolean. Access is denied outside the `local` environment by default.
- `navigationGroup()`, `navigationIcon()` (for every page, or one page) and `navigationSort()` plugin options. Pages are grouped under "Headroom" by default.
- `defaultRange()` plugin option (`7d`, `30d` or `90d`, default `30d`) and the `Range` enum shared by every page and widget.
- `tagLabel()` plugin option to control how ledger tags are shown, for example a tenant name instead of its ID.

[Unreleased]: https://github.com/phoenix1331/filament-headroom/commits/main
