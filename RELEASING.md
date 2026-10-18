# Releasing

Releases are cut from `main` by tagging. The `release` workflow publishes the GitHub release from `CHANGELOG.md`, and Packagist picks up the tag through its GitHub webhook.

## Checklist

1. Make sure `main` is green on the `tests` and `static analysis` workflows.
2. Run the full local gate: `composer check`.
3. Choose the version under [Semantic Versioning](https://semver.org). While on `0.x`, a minor bump may contain breaking changes; call them out under `### Changed` or `### Removed`.
4. In `CHANGELOG.md`, rename `## [Unreleased]` to `## [x.y.z] - YYYY-MM-DD`, add a fresh empty `## [Unreleased]` above it, and update the compare links at the bottom.
5. Commit: `chore: release x.y.z`.
6. Tag and push, signing the tag:

   ```bash
   git tag -s vx.y.z -m "vx.y.z"
   git push origin main vx.y.z
   ```

7. Check the `release` workflow created the GitHub release with the changelog section as its notes. It fails if `CHANGELOG.md` has no `## [x.y.z]` section for the tag.
8. Check [Packagist](https://packagist.org/packages/phoenix1331/filament-headroom) shows the new version.

## Before the first stable release

- Ledger and Headroom constraints must point at tagged Packagist versions, never path repositories.
- `git archive HEAD | tar -t` must list only shipped files; CI enforces this with `.github/scripts/check-archive.sh`.
