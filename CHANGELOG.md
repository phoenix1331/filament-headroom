# Changelog

All notable changes to `phoenix1331/filament-headroom` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `HeadroomPlugin::authorize()` to control who sees the dashboard, with a closure (receiving `$user`) or a boolean. Access is denied outside the `local` environment by default.

[Unreleased]: https://github.com/phoenix1331/filament-headroom/commits/main
