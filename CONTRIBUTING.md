# Contributing

Thanks for helping improve Headroom for Filament. Full contributor documentation (workbench, screenshots, composer scripts and translations) is coming before the first release.

Until then:

- Open an issue before starting anything large, so we can agree the approach.
- Run `composer check` before opening a pull request; it runs Pint, Larastan and the Pest suite with the 90% coverage gate.
- Commit messages follow lowercase [Conventional Commits](https://www.conventionalcommits.org) without scopes, for example `feat: add spend widget`. The Husky `commit-msg` hook enforces this after `npm install`.

Please follow the [code of conduct](CODE_OF_CONDUCT.md).
