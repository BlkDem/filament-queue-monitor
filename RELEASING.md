# Versioning and releases

## Policy

Versions follow [Semantic Versioning](https://semver.org/). The first planned
release is **0.1.0**; it is not published by this change.

- `0.MINOR.0`: features or breaking changes during initial development. Document breaking changes explicitly.
- `0.MINOR.PATCH`: backwards-compatible bug fixes and documentation corrections.
- From `1.0.0`: breaking changes require a major release.
- Prereleases use tags such as `v0.1.0-rc.1`.

The source of the Composer package version is the Git tag (`vX.Y.Z`), not a
`version` field in `composer.json`. `Support\Version` detects the Filament API;
it is not the package release version.

## Release checklist

1. Review the diff and confirm migrations and upgrade notes. Never rename an
   already released migration.
2. Move the relevant entries from `Unreleased` in `CHANGELOG.md` to a dated
   release section. For the first release, use `0.1.0`.
3. Run `composer validate --strict --no-check-lock`, `composer test`, and
   `composer audit` with freshly resolved dependencies.
4. Require passing compatibility and security CI jobs. Laravel 10 and 11 are
   legacy compatibility targets, not a promise of upstream security support.
5. Commit the release changes and create an annotated tag on that commit:

   ```bash
   git tag -a v0.1.0 -m "Release 0.1.0"
   ```

6. After maintainer approval, push the commit and tag, publish GitHub release
   notes from the changelog, and verify Packagist receives the tag.
7. Keep `Unreleased` open for the next changes.

`composer.lock` is intentionally ignored for this reusable library. Consumers
resolve their own dependency versions; CI resolves each supported combination.
Do not publish a local `vendor/` or lock file as a security fix for consumers:
applications must update their own dependencies.
