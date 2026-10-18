# Changelog

All notable changes are documented here. Versions follow Semantic Versioning;
see [RELEASING.md](RELEASING.md). No release has been tagged yet.

## [Unreleased]

### Fixed

- Parse database queue timestamps as Unix timestamps, including string values.
- Handle an empty configured database queue list safely.
- Scope database failure counts to the monitored queue connection.
- Accept array records from failed-job providers.
- Invalidate failed-job and queue caches after retrying or deleting failures.
- Avoid retry errors for malformed serialized command payloads.
- Honor string false values in the monitoring master switch.
- Preserve metrics when retention is zero, consistently with completed runs.
- Recognize legacy truncated metrics indexes during the job-column migration.
- Correct installation package name and table configuration documentation.
- Make test route registration and icon rendering compatible with Filament 4.

### Added

- Regression coverage for the audit findings.
- CI compatibility matrix for Laravel 10–12 and Filament 3–4, plus a separate
  security audit against freshly resolved current dependencies.
- Testbench 10 support to test Laravel 12.
- Release policy, changelog and distribution export exclusions.

### Upgrade notes

- Planned initial release: `0.1.0`; no tag or publication is created automatically.
- Set table names before migrating. Editing configuration does not rename tables.
- Migration index fixes apply when migrations execute; installations that already
  ran the job-column migration should inspect legacy indexes (see `AUDIT.md`).
- Updating this library does not update the application's framework lock file.
  Resolve security updates in the consuming application as well.
